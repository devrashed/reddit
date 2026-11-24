<?php

namespace App\Services\Ai;

use App\Models\BusinessIdea;
use Illuminate\Pagination\LengthAwarePaginator;

class BusinessIdeaQueryService
{
    public const PER_PAGE = 15;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, BusinessIdea>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return BusinessIdea::query()
            ->with([
                'sourcePost:id,title,subreddit_id,permalink,post_url',
                'sourcePost.subreddit:id,name,display_name',
                'painPoint:id,title,ai_analysis_id',
                'aiAnalysis:id,post_id,version,analysis_status',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('product_type', 'like', "%{$search}%");
                });
            })
            ->when(! empty($filters['subreddit_id']), function ($query) use ($filters): void {
                $query->whereHas('sourcePost', function ($query) use ($filters): void {
                    $query->where('subreddit_id', (int) $filters['subreddit_id']);
                });
            })
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
