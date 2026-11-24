<?php

namespace App\Services\Ai;

use App\Exceptions\AnalysisConflictException;
use App\Exceptions\DeepSeekException;
use App\Models\AiAnalysis;
use App\Models\RedditPost;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RedditPostAnalysisService
{
    public function __construct(
        private readonly DeepSeekClient $client,
        private readonly DeepSeekPromptBuilder $prompts,
        private readonly AnalysisInsightPersister $insights,
        private readonly AiSettings $aiSettings
    ) {}

    public function analyze(RedditPost $post, bool $reanalyze = false): AiAnalysis
    {
        if (blank(config('deepseek.api_key'))) {
            throw new DeepSeekException(
                'AI analysis is not configured. Add DEEPSEEK_API_KEY to the server environment.',
                422
            );
        }

        $lock = Cache::lock(
            'deepseek-analysis:'.$post->getKey(),
            max(30, (int) config('deepseek.lock_seconds', 300))
        );

        if (! $lock->get()) {
            throw new AnalysisConflictException(
                'An AI analysis is already being processed for this post.'
            );
        }

        $analysis = null;

        try {
            $post->refresh();
            $this->recoverStaleAnalysis($post);

            $latest = $post->aiAnalyses()->latest('id')->first();

            if ($latest !== null && in_array($latest->analysis_status, ['pending', 'processing'], true)) {
                throw new AnalysisConflictException(
                    'An AI analysis is already in progress for this post.'
                );
            }

            $completed = $post->aiAnalyses()
                ->where('analysis_status', 'completed')
                ->latest('version')
                ->first();

            if ($completed !== null && ! $reanalyze) {
                throw new AnalysisConflictException(
                    'A completed analysis already exists. Use re-analysis to create a new version.',
                    $completed
                );
            }

            $nextVersion = ((int) $post->aiAnalyses()->max('version')) + 1;

            try {
                $analysis = $post->aiAnalyses()->create([
                    'provider' => 'deepseek',
                    'model' => (string) config('deepseek.model', 'deepseek-chat'),
                    'analysis_type' => (string) config('deepseek.analysis_type', 'business_opportunity'),
                    'analysis_status' => 'processing',
                    'version' => $nextVersion,
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                throw new AnalysisConflictException(
                    'Another analysis request started for this post. Please retry.',
                    null,
                    $exception
                );
            }

            $post->loadMissing('subreddit:id,name');

            $commentsLimit = $this->aiSettings->commentsLimit();
            $comments = $post->comments()
                ->orderByDesc('score')
                ->orderBy('reddit_created_at')
                ->limit($commentsLimit)
                ->get([
                    'id',
                    'reddit_comment_id',
                    'parent_id',
                    'author',
                    'body',
                    'score',
                    'reddit_created_at',
                ]);
            $totalComments = $post->comments()->count();

            $messages = $this->prompts->build($post, $comments, $totalComments);
            $response = $this->client->analyze($messages);

            DB::transaction(function () use ($analysis, $response): void {
                $analysis->forceFill([
                    'provider' => $response['provider'],
                    'model' => $response['model'],
                    'analysis_result' => $response['result'],
                    'prompt_tokens' => $response['prompt_tokens'],
                    'completion_tokens' => $response['completion_tokens'],
                    'total_tokens' => $response['total_tokens'],
                    'analysis_status' => 'completed',
                    'analyzed_at' => now(),
                    'error_message' => null,
                ])->save();

                $this->insights->persist($analysis->refresh());
            });

            return $analysis->refresh();
        } catch (Throwable $exception) {
            if ($analysis !== null) {
                $message = $exception instanceof DeepSeekException
                    ? $exception->getMessage()
                    : 'AI analysis failed due to an unexpected server error.';

                $analysis->forceFill([
                    'analysis_status' => 'failed',
                    'error_message' => Str::limit($message, 2000),
                    'analyzed_at' => null,
                ])->save();

                if ($exception instanceof DeepSeekException) {
                    Log::warning('DeepSeek analysis failed.', [
                        'post_id' => $post->getKey(),
                        'analysis_id' => $analysis->getKey(),
                        'status_code' => $exception->statusCode,
                        'message' => Str::limit($exception->getMessage(), 500),
                    ]);
                } elseif (! ($exception instanceof AnalysisConflictException)) {
                    Log::error('Unexpected AI analysis failure.', [
                        'post_id' => $post->getKey(),
                        'analysis_id' => $analysis->getKey(),
                        'exception' => $exception::class,
                    ]);
                }
            }

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function recoverStaleAnalysis(RedditPost $post): void
    {
        $latest = $post->aiAnalyses()->latest('id')->first();

        if (
            $latest === null
            || ! in_array($latest->analysis_status, ['pending', 'processing'], true)
            || $latest->updated_at === null
        ) {
            return;
        }

        $staleBefore = now()->subMinutes(max(1, (int) config('deepseek.stale_processing_minutes', 10)));

        if ($latest->updated_at->lt($staleBefore)) {
            $latest->forceFill([
                'analysis_status' => 'failed',
                'error_message' => 'Analysis timed out or was interrupted before completion.',
            ])->save();
        }
    }
}
