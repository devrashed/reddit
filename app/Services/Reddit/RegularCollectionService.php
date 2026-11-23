<?php

namespace App\Services\Reddit;

use App\Enums\PostQualificationStatus;
use App\Enums\SubredditStatus;
use App\Models\CollectionLog;
use App\Models\RedditPost;
use App\Models\Subreddit;
use Carbon\CarbonInterface;
use Throwable;

class RegularCollectionService
{
    public function __construct(
        private readonly RedditApiClient $client,
        private readonly RedditPostSyncService $posts,
        private readonly PostQualificationService $qualification,
        private readonly QualifiedCommentDispatcher $commentDispatcher,
        private readonly CollectionLogService $logs
    ) {}

    public function run(Subreddit $subreddit): void
    {
        if ($subreddit->status !== SubredditStatus::Active) {
            return;
        }

        if (! $subreddit->hasCompletedInitialCollection()) {
            return;
        }

        $log = $this->logs->start($subreddit, CollectionLog::TYPE_REGULAR);
        $runStartedAt = now();

        $counts = [
            'fetched' => 0,
            'created' => 0,
            'updated' => 0,
            'qualified' => 0,
            'pending_checked' => 0,
            'newly_qualified' => 0,
            'comments' => 0,
            'failed' => 0,
        ];
        $notes = [];

        try {
            $this->syncRecentPosts($subreddit, $counts, $notes);

            $newlyQualified = $this->qualification->qualify($subreddit);
            $counts['pending_checked'] = $this->recheckPendingPosts(
                $subreddit,
                $runStartedAt,
                $counts,
                $notes
            );

            $newlyQualified += $this->qualification->qualify($subreddit);
            $counts['qualified'] = $newlyQualified;
            $counts['newly_qualified'] = $newlyQualified;

            $maxCommentPosts = max(0, (int) config('reddit.comments_max_posts_per_run', 10));
            $queued = $this->commentDispatcher->dispatchFor($subreddit, $maxCommentPosts);

            if ($queued > 0) {
                $notes[] = "Queued {$queued} comment collection job(s).";
            }

            $subreddit->forceFill(['last_collected_at' => now()])->save();

            $this->logs->complete($log, $counts, $notes);
        } catch (Throwable $exception) {
            $counts['failed'] = 1;

            $this->logs->fail($log, $exception, $counts, $notes);

            throw $exception;
        }
    }

    /**
     * @param  array<string, int>  $counts
     * @param  list<string>  $notes
     */
    private function syncRecentPosts(Subreddit $subreddit, array &$counts, array &$notes): void
    {
        $limit = max(1, min(100, (int) config('reddit.listing_limit', 100)));
        $maxPages = max(1, (int) config('reddit.regular_max_pages', 2));
        $cursor = null;
        $pages = 0;

        while ($pages < $maxPages) {
            $listing = $this->client->fetchSubredditListing($subreddit->name, $cursor, $limit);
            $children = $listing['data']['children'];

            if ($children === []) {
                break;
            }

            $result = $this->posts->sync($subreddit, $children);

            $counts['fetched'] += $result['fetched'];
            $counts['created'] += $result['inserted'];
            $counts['updated'] += $result['updated'];
            $pages++;

            $after = $listing['data']['after'] ?? null;
            $cursor = is_string($after) && $after !== '' ? $after : null;

            if ($cursor === null) {
                break;
            }
        }

        if ($pages >= $maxPages && $cursor !== null) {
            $notes[] = "Stopped after the configured regular-collection page limit of {$maxPages}.";
        }
    }

    /**
     * Refresh metrics for a bounded batch of older pending posts.
     *
     * @param  array<string, int>  $counts
     * @param  list<string>  $notes
     */
    private function recheckPendingPosts(
        Subreddit $subreddit,
        CarbonInterface $runStartedAt,
        array &$counts,
        array &$notes
    ): int {
        $maxPosts = max(0, (int) config('reddit.pending_recheck_max_posts', 300));

        if ($maxPosts === 0) {
            return 0;
        }

        $batchSize = max(1, min(100, (int) config('reddit.pending_recheck_batch_size', 100)));

        $pendingPosts = RedditPost::query()
            ->where('subreddit_id', $subreddit->getKey())
            ->where('qualification_status', PostQualificationStatus::Pending->value)
            ->where(function ($query) use ($runStartedAt): void {
                $query->whereNull('last_fetched_at')
                    ->orWhere('last_fetched_at', '<', $runStartedAt);
            })
            ->orderBy('pending_rechecked_at')
            ->orderBy('id')
            ->limit($maxPosts)
            ->get(['id', 'reddit_id']);

        $checked = 0;
        $missing = 0;

        foreach ($pendingPosts->chunk($batchSize) as $batch) {
            $ids = $batch->pluck('id')->all();
            $redditIds = $batch->pluck('reddit_id')->all();

            $info = $this->client->fetchPostInfo($redditIds);
            $children = $info['data']['children'];

            $result = $this->posts->sync($subreddit, $children);

            $counts['fetched'] += $result['fetched'];
            $counts['created'] += $result['inserted'];
            $counts['updated'] += $result['updated'];
            $missing += max(0, count($ids) - $result['fetched']);

            RedditPost::query()
                ->whereIn('id', $ids)
                ->update(['pending_rechecked_at' => now()]);

            $checked += count($ids);
        }

        if ($missing > 0) {
            $notes[] = "{$missing} pending post(s) were unavailable during metric refresh and remain pending.";
        }

        return $checked;
    }
}
