<?php

namespace App\Services\Reddit;

use App\Enums\PostQualificationStatus;
use App\Models\RedditPost;
use App\Models\Subreddit;

class PostQualificationService
{
    /**
     * Qualify pending posts that meet both engagement thresholds.
     *
     * Posts that do not meet both thresholds remain pending so they can be
     * re-evaluated after later metric refreshes.
     *
     * @return int Number of posts newly qualified
     */
    public function qualify(Subreddit $subreddit): int
    {
        $minScore = (int) config('reddit.qualification.min_score', 5);
        $minComments = (int) config('reddit.qualification.min_comments', 5);

        return RedditPost::query()
            ->where('subreddit_id', $subreddit->getKey())
            ->where('qualification_status', PostQualificationStatus::Pending->value)
            ->where('score', '>=', $minScore)
            ->where('num_comments', '>=', $minComments)
            ->update([
                'qualification_status' => PostQualificationStatus::Qualified->value,
                'qualified_at' => now(),
            ]);
    }
}
