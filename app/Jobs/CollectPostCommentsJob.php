<?php

namespace App\Jobs;

use App\Enums\CommentCollectionStatus;
use App\Enums\PostQualificationStatus;
use App\Enums\SubredditStatus;
use App\Models\CollectionLog;
use App\Models\RedditPost;
use App\Services\Reddit\CollectionLogService;
use App\Services\Reddit\CommentCollectionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CollectPostCommentsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries;

    /**
     * @var list<int>
     */
    public array $backoff;

    public int $timeout;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $postId)
    {
        $this->tries = (int) config('reddit.job_tries', 3);
        $this->backoff = array_values((array) config('reddit.job_backoff', [60, 300]));
        $this->timeout = (int) config('reddit.job_timeout', 600);
    }

    public function uniqueId(): string
    {
        return 'reddit-post-comments:'.$this->postId;
    }

    public function handle(CommentCollectionService $comments, CollectionLogService $logs): void
    {
        $post = RedditPost::query()->with('subreddit')->find($this->postId);

        if ($post === null || $post->subreddit === null) {
            return;
        }

        if ($post->subreddit->status !== SubredditStatus::Active) {
            return;
        }

        if ($post->qualification_status !== PostQualificationStatus::Qualified) {
            return;
        }

        if ($post->comments_collection_status === CommentCollectionStatus::Completed) {
            return;
        }

        $maxAttempts = max(1, (int) config('reddit.comments_max_attempts', 3));

        if ($post->comment_collection_attempts >= $maxAttempts) {
            return;
        }

        $lock = Cache::lock(
            'reddit-post-comments:'.$this->postId,
            (int) config('reddit.lock_seconds', 3600)
        );

        if (! $lock->get()) {
            return;
        }

        $log = null;

        try {
            $post->refresh();

            if (
                $post->qualification_status !== PostQualificationStatus::Qualified
                || $post->comments_collection_status === CommentCollectionStatus::Completed
                || $post->comment_collection_attempts >= $maxAttempts
            ) {
                return;
            }

            $log = $logs->start($post->subreddit, CollectionLog::TYPE_COMMENTS);
            $result = $comments->collect($post);

            $notes = array_merge(
                ["Comment collection for t3_{$post->reddit_id}."],
                $result['notes']
            );

            $logs->complete($log, [
                'comments' => $result['comments_fetched'],
                'failed' => 0,
            ], $notes);
        } catch (Throwable $exception) {
            if ($log !== null) {
                $logs->fail($log, $exception, [
                    'comments' => 0,
                    'failed' => 1,
                ], ["Comment collection failed for t3_{$post->reddit_id}."]);
            }

            throw $exception;
        } finally {
            $lock->release();
        }
    }
}
