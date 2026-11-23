<?php

namespace App\Services\Reddit;

use App\Enums\PostQualificationStatus;
use App\Models\RedditPost;
use App\Models\Subreddit;

class PostQualificationService
{
    /**
     * Qualify posts for comment collection.
     *
     * Posts that previously failed qualification are re-evaluated because
     * their score and comment count can change after later collections.
     *
     * @return int Number of posts newly qualified
     */
    public function qualify(Subreddit $subreddit): int
    {
        $minScore = (int) config('reddit.qualification.min_score', 1);
        $minComments = (int) config('reddit.qualification.min_comments', 1);

        $qualified = RedditPost::query()
            ->where('subreddit_id', $subreddit->getKey())
            ->where('qualification_status', '!=', PostQualificationStatus::Qualified->value)
            ->where('score', '>=', $minScore)
            ->where('num_comments', '>=', $minComments)
            ->update([
                'qualification_status' => PostQualificationStatus::Qualified->value,
                'qualified_at' => now(),
            ]);

        RedditPost::query()
            ->where('subreddit_id', $subreddit->getKey())
            ->where('qualification_status', PostQualificationStatus::Pending->value)
            ->update([
                'qualification_status' => PostQualificationStatus::Rejected->value,
            ]);

        return $qualified;
    }
}
