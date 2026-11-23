<?php

namespace App\Services\Reddit;

use App\Enums\CommentCollectionStatus;
use App\Enums\PostQualificationStatus;
use App\Enums\SubredditStatus;
use App\Models\CollectionLog;
use App\Models\RedditPost;
use App\Models\Subreddit;
use Throwable;

class RegularCollectionService
{
    public function __construct(
        private readonly RedditApiClient $client,
        private readonly RedditPostSyncService $posts,
        private readonly PostQualificationService $qualification,
        private readonly CommentCollectionService $comments,
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

        $counts = ['fetched' => 0, 'created' => 0, 'updated' => 0, 'qualified' => 0, 'comments' => 0];
        $notes = [];
        $commentErrors = [];

        try {
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

            $counts['qualified'] = $this->qualification->qualify($subreddit);

            $maxCommentPosts = max(0, (int) config('reddit.comments_max_posts_per_run', 10));
            $maxAttempts = max(1, (int) config('reddit.comments_max_attempts', 3));

            if ($maxCommentPosts > 0) {
                $commentPosts = RedditPost::query()
                    ->with('subreddit:id,name')
                    ->select([
                        'id',
                        'subreddit_id',
                        'reddit_id',
                        'permalink',
                        'score',
                        'comments_collection_status',
                        'comment_collection_attempts',
                    ])
                    ->where('subreddit_id', $subreddit->getKey())
                    ->where('qualification_status', PostQualificationStatus::Qualified->value)
                    ->whereIn('comments_collection_status', [
                        CommentCollectionStatus::Pending->value,
                        CommentCollectionStatus::Failed->value,
                        CommentCollectionStatus::Running->value,
                    ])
                    ->where('comment_collection_attempts', '<', $maxAttempts)
                    ->orderByDesc('score')
                    ->orderBy('id')
                    ->limit($maxCommentPosts)
                    ->get();

                foreach ($commentPosts as $post) {
                    try {
                        $result = $this->comments->collect($post);

                        $counts['comments'] += $result['comments_fetched'];
                        $notes = array_merge($notes, $result['notes']);
                    } catch (Throwable $exception) {
                        $message = "Comments for t3_{$post->reddit_id} failed: {$exception->getMessage()}";
                        $commentErrors[] = $message;
                        $notes[] = $message;
                    }
                }
            }

            if ($commentErrors !== []) {
                $notes[] = 'Some comment collections failed and may be retried on a later run if attempts remain.';
            }

            $subreddit->forceFill(['last_collected_at' => now()])->save();

            $this->logs->complete(
                $log,
                $counts,
                $notes,
                $commentErrors === [] ? null : implode("\n", $commentErrors)
            );
        } catch (Throwable $exception) {
            $this->logs->fail($log, $exception, $counts, $notes);

            throw $exception;
        }
    }
}
