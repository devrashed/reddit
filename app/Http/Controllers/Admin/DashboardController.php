<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommentCollectionStatus;
use App\Enums\PostQualificationStatus;
use App\Enums\SubredditStatus;
use App\Http\Controllers\Controller;
use App\Models\AiAnalysis;
use App\Models\CollectionLog;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the admin overview dashboard.
     */
    public function __invoke(): View
    {
        $stats = DB::selectOne(
            'select
                (select count(*) from subreddits) as total_subreddits,
                (select count(*) from subreddits where status = ?) as active_subreddits,
                (select count(*) from reddit_posts) as total_posts,
                (select count(*) from reddit_posts where qualification_status = ?) as qualified_posts,
                (select count(*) from reddit_posts where qualification_status = ? and comments_collection_status in (?, ?)) as awaiting_comments,
                (select count(*) from reddit_comments) as collected_comments,
                (select count(*) from ai_analyses) as total_analyses,
                (select count(*) from pain_points) as total_pain_points,
                (select count(*) from business_ideas) as total_business_ideas,
                (select coalesce(sum(total_tokens), 0) from ai_analyses where analysis_status = ?) as deepseek_tokens',
            [
                SubredditStatus::Active->value,
                PostQualificationStatus::Qualified->value,
                PostQualificationStatus::Qualified->value,
                CommentCollectionStatus::Pending->value,
                CommentCollectionStatus::Failed->value,
                'completed',
            ]
        );

        $metrics = [
            [
                'label' => 'Total Subreddits',
                'value' => (int) $stats->total_subreddits,
                'icon' => 'bi-collection',
                'tone' => 'primary',
                'route' => 'admin.subreddits.index',
            ],
            [
                'label' => 'Active Subreddits',
                'value' => (int) $stats->active_subreddits,
                'icon' => 'bi-broadcast',
                'tone' => 'success',
                'route' => 'admin.subreddits.index',
            ],
            [
                'label' => 'Total Reddit Posts',
                'value' => (int) $stats->total_posts,
                'icon' => 'bi-file-earmark-text',
                'tone' => 'primary',
                'route' => 'admin.reddit-posts.index',
            ],
            [
                'label' => 'Qualified Posts',
                'value' => (int) $stats->qualified_posts,
                'icon' => 'bi-patch-check',
                'tone' => 'success',
                'route' => 'admin.reddit-posts.index',
            ],
            [
                'label' => 'Awaiting Comments',
                'value' => (int) $stats->awaiting_comments,
                'icon' => 'bi-chat-square-dots',
                'tone' => 'warning',
                'route' => 'admin.reddit-posts.index',
            ],
            [
                'label' => 'Collected Comments',
                'value' => (int) $stats->collected_comments,
                'icon' => 'bi-chat-left-text',
                'tone' => 'info',
                'route' => 'admin.reddit-posts.index',
            ],
            [
                'label' => 'AI Analyses',
                'value' => (int) $stats->total_analyses,
                'icon' => 'bi-cpu',
                'tone' => 'primary',
                'route' => 'admin.ai-analysis.index',
            ],
            [
                'label' => 'Pain Points',
                'value' => (int) $stats->total_pain_points,
                'icon' => 'bi-bandaid',
                'tone' => 'danger',
                'route' => 'admin.pain-points.index',
            ],
            [
                'label' => 'Business Ideas',
                'value' => (int) $stats->total_business_ideas,
                'icon' => 'bi-lightbulb',
                'tone' => 'warning',
                'route' => 'admin.business-ideas.index',
            ],
            [
                'label' => 'DeepSeek Tokens',
                'value' => (int) $stats->deepseek_tokens,
                'icon' => 'bi-activity',
                'tone' => 'info',
                'route' => 'admin.ai-analysis.index',
            ],
        ];

        $recentLogs = CollectionLog::query()
            ->with('subreddit:id,name,display_name')
            ->latest('id')
            ->limit(5)
            ->get();

        $recentAnalyses = AiAnalysis::query()
            ->with('redditPost:id,title')
            ->latest('id')
            ->limit(5)
            ->get();

        return view('admin.dashboard', [
            'metrics' => $metrics,
            'recentLogs' => $recentLogs,
            'recentAnalyses' => $recentAnalyses,
            'system' => [
                'environment' => app()->environment(),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'timezone' => config('app.timezone'),
            ],
        ]);
    }
}
