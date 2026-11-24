<?php

namespace App\Services\Ai;

use App\Models\AiAnalysis;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AiAnalysisQueryService
{
    public const PER_PAGE = 15;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, AiAnalysis>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return AiAnalysis::query()
            ->with([
                'redditPost:id,title,subreddit_id,permalink,post_url',
                'redditPost.subreddit:id,name,display_name',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('redditPost', function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%");
                });
            })
            ->when(! empty($filters['subreddit_id']), function ($query) use ($filters): void {
                $query->whereHas('redditPost', function ($query) use ($filters): void {
                    $query->where('subreddit_id', (int) $filters['subreddit_id']);
                });
            })
            ->when(! empty($filters['status']), fn ($query) => $query->where('analysis_status', $filters['status']))
            ->when(! empty($filters['date_from']), fn ($query) => $query->whereDate(DB::raw('coalesce(analyzed_at, created_at)'), '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($query) => $query->whereDate(DB::raw('coalesce(analyzed_at, created_at)'), '<=', $filters['date_to']))
            ->latest('created_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
