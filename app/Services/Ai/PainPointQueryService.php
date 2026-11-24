<?php

namespace App\Services\Ai;

use App\Models\PainPoint;
use Illuminate\Pagination\LengthAwarePaginator;

class PainPointQueryService
{
    public const PER_PAGE = 15;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PainPoint>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return PainPoint::query()
            ->with([
                'sourcePost:id,title,subreddit_id,permalink,post_url',
                'sourcePost.subreddit:id,name,display_name',
                'aiAnalysis:id,post_id,version,analysis_status',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('root_cause', 'like', "%{$search}%");
                });
            })
            ->when(! empty($filters['subreddit_id']), function ($query) use ($filters): void {
                $query->whereHas('sourcePost', function ($query) use ($filters): void {
                    $query->where('subreddit_id', (int) $filters['subreddit_id']);
                });
            })
            ->when(! empty($filters['severity']), function ($query) use ($filters): void {
                if ($filters['severity'] === 'not_assessed') {
                    $query->whereNull('severity');
                } else {
                    $query->where('severity', $filters['severity']);
                }
            })
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
