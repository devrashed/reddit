<?php

namespace App\Services\Ai;

use App\Exceptions\DeepSeekException;
use App\Support\AnalysisReportSchema;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Throwable;

class DeepSeekClient
{
    public function __construct(private readonly AiSettings $aiSettings) {}

    /**
     * Send the prepared prompt to DeepSeek and return the normalized report.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{result: array<string, mixed>, provider: string, model: string, prompt_tokens: int|null, completion_tokens: int|null, total_tokens: int|null}
     */
    public function analyze(array $messages): array
    {
        $apiKey = trim((string) config('deepseek.api_key'));

        if ($apiKey === '') {
            throw new DeepSeekException(
                'AI analysis is not configured. Add DEEPSEEK_API_KEY to the server environment.',
                422
            );
        }

        $url = rtrim((string) config('deepseek.base_url', 'https://api.deepseek.com'), '/')
            .'/'.ltrim((string) config('deepseek.path', '/chat/completions'), '/');
        $timeout = $this->aiSettings->timeout();
        $model = $this->aiSettings->model();
        $connectTimeout = max(1, min($timeout, (int) config('deepseek.connect_timeout', 10)));
        $attempts = max(1, (int) config('deepseek.retry_attempts', 2));

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = Http::timeout($timeout)
                    ->connectTimeout($connectTimeout)
                    ->withToken($apiKey)
                    ->acceptJson()
                    ->post($url, [
                        'model' => $model,
                        'messages' => $messages,
                        'temperature' => (float) config('deepseek.temperature', 0.2),
                        'max_tokens' => (int) config('deepseek.max_output_tokens', 2500),
                        'stream' => false,
                        ...((bool) config('deepseek.json_mode', true)
                            ? ['response_format' => ['type' => 'json_object']]
                            : []),
                    ]);
            } catch (ConnectionException $exception) {
                if ($attempt < $attempts) {
                    $this->pause($this->backoffSeconds($attempt), $attempt, null, $exception);

                    continue;
                }

                throw new DeepSeekException(
                    'The AI provider could not be reached. Please try again.',
                    502,
                    $exception
                );
            }

            if ($response->successful()) {
                return $this->parseResponse($response);
            }

            $status = $response->status();

            if ($status === 401) {
                throw new DeepSeekException(
                    'The AI provider rejected the API credentials. Check DEEPSEEK_API_KEY.',
                    422
                );
            }

            if ($status === 402) {
                throw new DeepSeekException(
                    'The AI provider account has insufficient credits.',
                    422
                );
            }

            if ($status === 403) {
                throw new DeepSeekException(
                    'The AI provider denied access to this request.',
                    422
                );
            }

            if ($status === 429 || $status >= 500) {
                if ($attempt < $attempts) {
                    $delay = $this->retryAfterSeconds($response) ?? $this->backoffSeconds($attempt);
                    $this->pause($delay, $attempt, $status);

                    continue;
                }

                throw new DeepSeekException(
                    $status === 429
                        ? 'The AI provider rate limit was reached. Please try again later.'
                        : 'The AI provider is temporarily unavailable. Please try again later.',
                    $status === 429 ? 429 : 502
                );
            }

            throw new DeepSeekException(
                "The AI provider rejected the analysis request (HTTP {$status}).",
                422
            );
        }

        throw new DeepSeekException('The AI analysis request failed.', 502);
    }

    /**
     * @return array{result: array<string, mixed>, provider: string, model: string, prompt_tokens: int|null, completion_tokens: int|null, total_tokens: int|null}
     */
    private function parseResponse(Response $response): array
    {
        $json = $response->json();

        if (! is_array($json)) {
            throw new DeepSeekException('The AI provider returned an invalid JSON response.', 502);
        }

        $content = data_get($json, 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new DeepSeekException('The AI provider returned an empty analysis result.', 502);
        }

        $result = $this->decodeAnalysis($content);
        $usage = is_array($json['usage'] ?? null) ? $json['usage'] : [];
        $responseModel = data_get($json, 'model');

        return [
            'result' => $result,
            'provider' => 'deepseek',
            'model' => is_string($responseModel) && $responseModel !== ''
                ? $responseModel
                : $this->aiSettings->model(),
            'prompt_tokens' => $this->tokenValue($usage, 'prompt_tokens'),
            'completion_tokens' => $this->tokenValue($usage, 'completion_tokens'),
            'total_tokens' => $this->tokenValue($usage, 'total_tokens'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeAnalysis(string $content): array
    {
        $content = trim($content);

        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*/i', '', $content) ?? $content;
            $content = preg_replace('/\s*```$/', '', $content) ?? $content;
            $content = trim($content);
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            $start = strpos($content, '{');
            $end = strrpos($content, '}');

            if ($start !== false && $end !== false && $end > $start) {
                $decoded = json_decode(substr($content, $start, $end - $start + 1), true);
            }
        }

        if (! is_array($decoded)) {
            throw new DeepSeekException('The AI provider returned an invalid analysis format.', 502);
        }

        return $this->validateResult($decoded);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function validateResult(array $result): array
    {
        $normalized = [];

        foreach (array_keys(AnalysisReportSchema::SECTIONS) as $key) {
            if (! array_key_exists($key, $result)) {
                throw new DeepSeekException(
                    'The AI provider returned an incomplete analysis report.',
                    502
                );
            }

            if (in_array($key, AnalysisReportSchema::LIST_SECTIONS, true)) {
                if (! is_array($result[$key])) {
                    throw new DeepSeekException(
                        'The AI provider returned an invalid analysis report structure.',
                        502
                    );
                }

                $normalized[$key] = array_values(array_filter(array_map(
                    static fn (mixed $value): string => trim((string) $value),
                    $result[$key]
                ), static fn (string $value): bool => $value !== ''));
            } else {
                if (! is_scalar($result[$key])) {
                    throw new DeepSeekException(
                        'The AI provider returned an invalid analysis report structure.',
                        502
                    );
                }

                $normalized[$key] = trim((string) $result[$key]);
            }
        }

        $classification = $result['evidence_classification'] ?? null;

        if (! is_array($classification)) {
            throw new DeepSeekException(
                'The AI provider returned an incomplete evidence classification.',
                502
            );
        }

        $normalizedClassification = [];

        foreach (array_keys(AnalysisReportSchema::CLASSIFICATION_SECTIONS) as $key) {
            if (! isset($classification[$key]) || ! is_array($classification[$key])) {
                throw new DeepSeekException(
                    'The AI provider returned an incomplete evidence classification.',
                    502
                );
            }

            $normalizedClassification[$key] = array_values(array_filter(array_map(
                static fn (mixed $value): string => trim((string) $value),
                $classification[$key]
            ), static fn (string $value): bool => $value !== ''));
        }

        $normalized['evidence_classification'] = $normalizedClassification;

        $hasContent = collect($normalized)
            ->except('evidence_classification')
            ->contains(static fn (mixed $value): bool => is_array($value) ? $value !== [] : $value !== '');

        if (! $hasContent) {
            throw new DeepSeekException('The AI provider returned an empty analysis result.', 502);
        }

        return $normalized;
    }

    private function tokenValue(array $usage, string $key): ?int
    {
        $value = $usage[$key] ?? null;

        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    private function pause(float $seconds, int $attempt, ?int $status, ?Throwable $exception = null): void
    {
        Log::warning('Retrying DeepSeek request.', [
            'attempt' => $attempt,
            'status' => $status,
            'delay_seconds' => $seconds,
            'error' => $exception?->getMessage(),
        ]);

        if ($seconds > 0) {
            Sleep::sleep($seconds);
        }
    }

    private function backoffSeconds(int $attempt): float
    {
        $base = max(0.1, (int) config('deepseek.retry_base_delay_ms', 1000) / 1000);

        return min(20.0, $base * (2 ** max(0, $attempt - 1)));
    }

    private function retryAfterSeconds(Response $response): ?float
    {
        $retryAfter = trim((string) $response->header('Retry-After'));

        if ($retryAfter === '') {
            return null;
        }

        if (is_numeric($retryAfter)) {
            return (float) min(60, max(1, (int) $retryAfter));
        }

        try {
            $seconds = Carbon::parse($retryAfter)->getTimestamp() - now()->getTimestamp();

            return (float) min(60, max(1, $seconds));
        } catch (Throwable) {
            return null;
        }
    }
}
