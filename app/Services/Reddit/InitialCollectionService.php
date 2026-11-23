<?php

namespace App\Services\Reddit;

use App\Enums\InitialCollectionStatus;
use App\Enums\SubredditStatus;
use App\Models\CollectionLog;
use App\Models\Subreddit;
use Throwable;

class InitialCollectionService
{
    public function __construct(
        private readonly RedditApiClient $client,
        private readonly RedditPostSyncService $posts,
        private readonly PostQualificationService $qualification,
        private readonly QualifiedCommentDispatcher $commentDispatcher,
        private readonly CollectionLogService $logs,
        private readonly RedditSettings $settings
    ) {}

    public function run(Subreddit $subreddit): void
    {
        if ($subreddit->status !== SubredditStatus::Active) {
            return;
        }

        if ($subreddit->hasCompletedInitialCollection()) {
            return;
        }

        $log = $this->logs->start($subreddit, CollectionLog::TYPE_INITIAL);

        $months = $this->settings->historicalMonths();
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
        $skipped = 0;

        try {
            $subreddit->forceFill([
                'initial_collection_status' => InitialCollectionStatus::Running->value,
                'initial_collection_started_at' => $subreddit->initial_collection_started_at ?? now(),
            ])->save();

            $cutoff = now()->subMonthsNoOverflow($months);
            $cursor = $subreddit->initial_collection_cursor;
            $pages = $subreddit->initial_collection_pages;
            $totalFetched = $subreddit->initial_collection_posts_fetched;
            $pagesThisRun = 0;
            $endedReason = null;

            $limit = max(1, min(100, (int) config('reddit.listing_limit', 100)));
            $maxPages = max(1, (int) config('reddit.initial_max_pages', 30));

            while ($pagesThisRun < $maxPages) {
                $listing = $this->client->fetchSubredditListing($subreddit->name, $cursor, $limit);
                $children = $listing['data']['children'];

                if ($children === []) {
                    $endedReason = 'empty';

                    break;
                }

                $result = $this->posts->sync($subreddit, $children, $cutoff);

                $counts['fetched'] += $result['fetched'];
                $counts['created'] += $result['inserted'];
                $counts['updated'] += $result['updated'];
                $skipped += $result['skipped'];
                $totalFetched += $result['fetched'];

                $pagesThisRun++;
                $pages++;
                $after = $listing['data']['after'] ?? null;
                $cursor = is_string($after) && $after !== '' ? $after : null;

                $subreddit->forceFill([
                    'initial_collection_cursor' => $cursor,
                    'initial_collection_pages' => $pages,
                    'initial_collection_posts_fetched' => $totalFetched,
                ])->save();

                if ($result['oldest'] !== null && $result['oldest']->lt($cutoff)) {
                    $endedReason = 'cutoff';

                    break;
                }

                if ($cursor === null) {
                    $endedReason = 'end';

                    break;
                }
            }

            if ($endedReason === null) {
                $endedReason = 'page_limit';
            }

            $notes = array_merge($notes, match ($endedReason) {
                'cutoff' => ["Pagination stopped at the configured {$months}-month cutoff."],
                'end' => [
                    "Reddit's listing ended after {$pagesThisRun} page(s) before the full {$months}-month window was reached. Historical coverage may be incomplete.",
                ],
                'empty' => ['Reddit returned an empty listing. Historical coverage may be incomplete.'],
                'page_limit' => [
                    "Stopped after the configured maximum of {$maxPages} page(s) to bound request volume. Historical coverage may be incomplete.",
                ],
                default => [],
            });

            if ($skipped > 0) {
                $notes[] = "{$skipped} post(s) older than the {$months}-month cutoff were skipped.";
            }

            $newlyQualified = $this->qualification->qualify($subreddit);
            $counts['qualified'] = $newlyQualified;
            $counts['newly_qualified'] = $newlyQualified;

            $maxCommentPosts = max(0, (int) config('reddit.comments_max_posts_per_run', 10));
            $queued = $this->commentDispatcher->dispatchFor($subreddit, $maxCommentPosts);

            if ($queued > 0) {
                $notes[] = "Queued {$queued} comment collection job(s).";
            }

            $subreddit->forceFill([
                'initial_collection_status' => InitialCollectionStatus::Completed->value,
                'initial_collection_completed_at' => now(),
                'last_collected_at' => now(),
            ])->save();

            $this->logs->complete($log, $counts, $notes);
        } catch (Throwable $exception) {
            $subreddit->forceFill([
                'initial_collection_status' => InitialCollectionStatus::Failed->value,
            ])->save();

            $counts['failed'] = 1;

            $this->logs->fail($log, $exception, $counts, $notes);

            throw $exception;
        }
    }
}
