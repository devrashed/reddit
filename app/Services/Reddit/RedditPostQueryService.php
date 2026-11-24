<?php

namespace App\Services\Reddit;

use App\Enums\AiAnalysisStatus;
use App\Models\AiAnalysis;
use App\Models\RedditPost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class RedditPostQueryService
{
    public const PER_PAGE = 15;

    public const SORT_NEWEST = 'newest';

    public const SORT_OLDEST = 'oldest';

    public const SORT_HIGHEST_SCORE = 'highest_score';

    public const SORT_MOST_COMMENTS = 'most_comments';

    public const SORT_RECENTLY_UPDATED = 'recently_updated';

    public const SORTS = [
        self::SORT_NEWEST,
        self::SORT_OLDEST,
        self::SORT_HIGHEST_SCORE,
        self::SORT_MOST_COMMENTS,
        self::SORT_RECENTLY_UPDATED,
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, RedditPost>
     */
    public function paginate(array $filters, string $sort = self::SORT_NEWEST): LengthAwarePaginator
    {
        $query = RedditPost::query()
            ->with(['subreddit:id,name,display_name'])
            ->withCount('comments')
            ->addSelect([
                'latest_ai_analysis_status' => AiAnalysis::query()
                    ->select('analysis_status')
                    ->whereColumn('post_id', 'reddit_posts.id')
                    ->latest('id')
                    ->limit(1),
                'latest_completed_ai_version' => AiAnalysis::query()
                    ->select('version')
                    ->whereColumn('post_id', 'reddit_posts.id')
                    ->where('analysis_status', 'completed')
                    ->latest('version')
                    ->limit(1),
            ]);

        $this->applyFilters($query, $filters);
        $this->applySort($query, $sort);

        return $query
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * @param  Builder<RedditPost>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('selftext', 'like', "%{$search}%")
                    ->orWhere('author', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['subreddit_id'])) {
            $query->where('subreddit_id', (int) $filters['subreddit_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('reddit_created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('reddit_created_at', '<=', $filters['date_to']);
        }

        if (isset($filters['min_score']) && $filters['min_score'] !== '') {
            $query->where('score', '>=', (int) $filters['min_score']);
        }

        if (isset($filters['min_comments']) && $filters['min_comments'] !== '') {
            $query->where('num_comments', '>=', (int) $filters['min_comments']);
        }

        if (! empty($filters['qualification_status'])) {
            $query->where('qualification_status', $filters['qualification_status']);
        }

        $aiStatus = $filters['ai_status'] ?? null;

        if ($aiStatus === AiAnalysisStatus::NotAnalyzed->value) {
            $query->whereDoesntHave('aiAnalyses');
        } elseif (! empty($aiStatus)) {
            $query->whereRaw(
                '(select analysis_status from ai_analyses where ai_analyses.post_id = reddit_posts.id order by ai_analyses.id desc limit 1) = ?',
                [$aiStatus]
            );
        }
    }

    /**
     * @param  Builder<RedditPost>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            self::SORT_OLDEST => $query
                ->orderBy('reddit_created_at')
                ->orderBy('id'),
            self::SORT_HIGHEST_SCORE => $query
                ->orderByDesc('score')
                ->orderByDesc('id'),
            self::SORT_MOST_COMMENTS => $query
                ->orderByDesc('num_comments')
                ->orderByDesc('id'),
            self::SORT_RECENTLY_UPDATED => $query
                ->orderByDesc('updated_at')
                ->orderByDesc('id'),
            default => $query
                ->orderByDesc('reddit_created_at')
                ->orderByDesc('id'),
        };
    }
}
