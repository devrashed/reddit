<?php

namespace App\Services\Reddit;

use App\Exceptions\RedditCollectionException;
use App\Models\RedditPost;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Throwable;

class RedditApiClient
{
    private ?float $lastRequestAt = null;

    public function __construct(private readonly RedditSettings $settings) {}

    /**
     * Fetch one page of a subreddit listing.
     *
     * @return array<string, mixed>
     */
    public function fetchSubredditListing(string $subredditName, ?string $after = null, int $limit = 100): array
    {
        $query = [
            'limit' => max(1, min(100, $limit)),
            'raw_json' => 1,
        ];

        if ($after !== null && $after !== '') {
            $query['after'] = $after;
        }

        $url = $this->baseUrl().'/r/'.rawurlencode($subredditName).'.json';
        $json = $this->request($url, $query, "r/{$subredditName} listing");

        if (! is_array($json['data']['children'] ?? null)) {
            throw new RedditCollectionException("Reddit returned an unexpected listing structure for r/{$subredditName}.");
        }

        return $json;
    }

    /**
     * Fetch fresh metrics for a batch of known Reddit posts.
     *
     * @param  list<string>  $redditIds
     * @return array<string, mixed>
     */
    public function fetchPostInfo(array $redditIds): array
    {
        $ids = [];

        foreach ($redditIds as $redditId) {
            $redditId = trim((string) $redditId);

            if ($redditId !== '') {
                $ids[] = str_starts_with($redditId, 't3_') ? $redditId : 't3_'.$redditId;
            }
        }

        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return ['kind' => 'Listing', 'data' => ['children' => []]];
        }

        $json = $this->request($this->baseUrl().'/api/info.json', [
            'id' => implode(',', array_slice($ids, 0, 100)),
            'raw_json' => 1,
        ], 'post info');

        if (! is_array($json['data']['children'] ?? null)) {
            throw new RedditCollectionException('Reddit returned an unexpected post-info structure.');
        }

        return $json;
    }

    /**
     * Fetch the comments listing for a post.
     *
     * @return array<int, mixed>
     */
    public function fetchPostComments(RedditPost $post): array
    {
        $permalink = trim((string) $post->permalink);

        if ($permalink === '') {
            $permalink = '/r/'.rawurlencode((string) $post->subreddit?->name)."/comments/{$post->reddit_id}";
        }

        $url = str_starts_with($permalink, 'http')
            ? $permalink
            : $this->baseUrl().'/'.ltrim($permalink, '/');

        $url = rtrim($url, '/').'.json';

        $json = $this->request($url, [
            'limit' => (int) config('reddit.comments_request_limit', 500),
            'depth' => (int) config('reddit.comments_request_depth', 10),
            'raw_json' => 1,
        ], "comments for t3_{$post->reddit_id}");

        if (! is_array($json[1]['data']['children'] ?? null)) {
            throw new RedditCollectionException("Reddit returned an unexpected comments structure for t3_{$post->reddit_id}.");
        }

        return $json;
    }

    /**
     * Request additional comments referenced by a "more" placeholder.
     *
     * @param  list<string>  $commentIds
     * @return array<string, mixed>
     */
    public function fetchMoreComments(RedditPost $post, array $commentIds): array
    {
        $commentIds = array_values(array_filter(array_map(
            static fn (mixed $id): string => trim((string) $id),
            $commentIds
        )));

        if ($commentIds === []) {
            return ['json' => ['data' => ['things' => []]]];
        }

        $json = $this->request($this->baseUrl().'/api/morechildren.json', [
            'api_type' => 'json',
            'link_id' => 't3_'.$post->reddit_id,
            'children' => implode(',', array_slice($commentIds, 0, 100)),
            'limit_children' => 'false',
            'raw_json' => 1,
        ], "additional comments for t3_{$post->reddit_id}");

        if (! is_array($json['json']['data']['things'] ?? null)) {
            throw new RedditCollectionException("Reddit returned an unexpected additional-comments structure for t3_{$post->reddit_id}.");
        }

        return $json;
    }

    /**
     * Perform a request with bounded retries and exponential backoff.
     *
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     */
    private function request(string $url, array $query, string $context): array
    {
        $attempts = $this->settings->retryAttempts();
        $timeout = $this->settings->httpTimeout();
        $connectTimeout = max(1, min($timeout, (int) config('reddit.connect_timeout', 5)));

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $this->throttle();

            try {
                $response = Http::timeout($timeout)
                    ->connectTimeout($connectTimeout)
                    ->withUserAgent((string) config('reddit.user_agent'))
                    ->acceptJson()
                    ->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
                    ->get($url, $query);
            } catch (ConnectionException $exception) {
                if ($attempt < $attempts) {
                    $this->pause($this->backoffSeconds($attempt), $context, null, $attempt, $exception);

                    continue;
                }

                throw new RedditCollectionException(
                    "Network failure while fetching {$context}: {$exception->getMessage()}",
                    0,
                    $exception
                );
            }

            if ($response->successful()) {
                $json = $response->json();

                if (is_array($json)) {
                    return $json;
                }

                if ($attempt < $attempts) {
                    $this->pause($this->backoffSeconds($attempt), $context, $response, $attempt);

                    continue;
                }

                throw new RedditCollectionException("Reddit returned invalid JSON while fetching {$context}.");
            }

            $status = $response->status();

            if (in_array($status, [401, 403], true)) {
                throw new RedditCollectionException("Reddit denied access while fetching {$context} (HTTP {$status}).");
            }

            if ($status === 404) {
                throw new RedditCollectionException("Reddit returned not found while fetching {$context} (HTTP 404).");
            }

            if ($status === 429 || $status >= 500) {
                if ($attempt < $attempts) {
                    $delay = $this->retryAfterSeconds($response) ?? $this->backoffSeconds($attempt);
                    $this->pause($delay, $context, $response, $attempt);

                    continue;
                }

                throw new RedditCollectionException(
                    "Reddit request for {$context} failed with HTTP {$status} after {$attempts} attempt(s)."
                );
            }

            throw new RedditCollectionException("Reddit request for {$context} failed with HTTP {$status}.");
        }

        throw new RedditCollectionException("Reddit request for {$context} failed.");
    }

    private function throttle(): void
    {
        $delayMs = max(0, (int) config('reddit.request_delay_ms', 1000));

        if ($this->lastRequestAt !== null && $delayMs > 0) {
            $elapsedMs = (microtime(true) - $this->lastRequestAt) * 1000;
            $remainingMs = $delayMs - $elapsedMs;

            if ($remainingMs > 0) {
                Sleep::for($remainingMs)->milliseconds();
            }
        }

        $this->lastRequestAt = microtime(true);
    }

    private function pause(float $seconds, string $context, ?Response $response, int $attempt, ?Throwable $exception = null): void
    {
        Log::warning('Retrying Reddit request.', [
            'context' => $context,
            'attempt' => $attempt,
            'status' => $response?->status(),
            'delay_seconds' => $seconds,
            'error' => $exception?->getMessage(),
        ]);

        if ($seconds > 0) {
            Sleep::sleep($seconds);
        }
    }

    private function backoffSeconds(int $attempt): float
    {
        $base = max(0.1, (int) config('reddit.retry_base_delay_ms', 1000) / 1000);

        return min(30.0, $base * (2 ** max(0, $attempt - 1)));
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

    private function baseUrl(): string
    {
        return rtrim((string) config('reddit.base_url', 'https://www.reddit.com'), '/');
    }
}
