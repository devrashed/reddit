<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubredditStatus;
use App\Http\Controllers\Controller;
use App\Models\BusinessIdea;
use App\Models\CollectionLog;
use App\Models\PainPoint;
use App\Models\RedditPost;
use App\Models\Subreddit;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the admin overview dashboard.
     */
    public function __invoke(): View
    {
        $metrics = [
            [
                'label' => 'Reddit Posts',
                'value' => RedditPost::count(),
                'icon' => 'bi-file-earmark-text',
                'tone' => 'primary',
                'route' => 'admin.reddit-posts.index',
            ],
            [
                'label' => 'Pain Points',
                'value' => PainPoint::count(),
                'icon' => 'bi-bandaid',
                'tone' => 'danger',
                'route' => 'admin.pain-points.index',
            ],
            [
                'label' => 'Business Ideas',
                'value' => BusinessIdea::count(),
                'icon' => 'bi-lightbulb',
                'tone' => 'warning',
                'route' => 'admin.business-ideas.index',
            ],
            [
                'label' => 'Active Subreddits',
                'value' => Subreddit::where('status', SubredditStatus::Active->value)->count(),
                'icon' => 'bi-collection',
                'tone' => 'success',
                'route' => 'admin.subreddits.index',
            ],
        ];

        $recentLogs = CollectionLog::query()
            ->with('subreddit')
            ->latest('id')
            ->limit(5)
            ->get();

        return view('admin.dashboard', [
            'metrics' => $metrics,
            'recentLogs' => $recentLogs,
            'system' => [
                'environment' => app()->environment(),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'timezone' => config('app.timezone'),
            ],
        ]);
    }
}
