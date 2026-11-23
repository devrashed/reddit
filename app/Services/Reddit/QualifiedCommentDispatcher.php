<?php

namespace App\Services\Reddit;

use App\Enums\CommentCollectionStatus;
use App\Enums\PostQualificationStatus;
use App\Jobs\CollectPostCommentsJob;
use App\Models\RedditPost;
use App\Models\Subreddit;

class QualifiedCommentDispatcher
{
    /**
     * Dispatch comment collection jobs for qualified posts that still need them.
     *
     * @return int Number of jobs dispatched
     */
    public function dispatchFor(Subreddit $subreddit, int $limit): int
    {
        if ($limit <= 0) {
            return 0;
        }

        $maxAttempts = max(1, (int) config('reddit.comments_max_attempts', 3));

        $postIds = RedditPost::query()
            ->where('subreddit_id', $subreddit->getKey())
            ->where('qualification_status', PostQualificationStatus::Qualified->value)
            ->whereIn('comments_collection_status', [
                CommentCollectionStatus::Pending->value,
                CommentCollectionStatus::Failed->value,
            ])
            ->where('comment_collection_attempts', '<', $maxAttempts)
            ->orderByDesc('score')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $dispatched = 0;

        foreach ($postIds as $postId) {
            CollectPostCommentsJob::dispatch((int) $postId);
            $dispatched++;
        }

        return $dispatched;
    }
}
