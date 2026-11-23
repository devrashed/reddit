<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin Sidebar Navigation
    |--------------------------------------------------------------------------
    |
    | Each section contains the menu items rendered by the admin sidebar.
    | The "active" key is passed to the route matcher to highlight the
    | current page.
    |
    */

    'sidebar' => [
        [
            'label' => null,
            'items' => [
                [
                    'label' => 'Overview',
                    'route' => 'admin.dashboard',
                    'icon' => 'bi-speedometer2',
                    'active' => 'admin.dashboard',
                ],
            ],
        ],
        [
            'label' => 'Discovery',
            'items' => [
                [
                    'label' => 'Reddit Posts',
                    'route' => 'admin.reddit-posts.index',
                    'icon' => 'bi-file-earmark-text',
                    'active' => 'admin.reddit-posts.*',
                ],
                [
                    'label' => 'Pain Points',
                    'route' => 'admin.pain-points.index',
                    'icon' => 'bi-bandaid',
                    'active' => 'admin.pain-points.*',
                ],
                [
                    'label' => 'Business Ideas',
                    'route' => 'admin.business-ideas.index',
                    'icon' => 'bi-lightbulb',
                    'active' => 'admin.business-ideas.*',
                ],
                [
                    'label' => 'Subreddits',
                    'route' => 'admin.subreddits.index',
                    'icon' => 'bi-collection',
                    'active' => 'admin.subreddits.*',
                ],
            ],
        ],
        [
            'label' => 'Intelligence',
            'items' => [
                [
                    'label' => 'AI Analysis',
                    'route' => 'admin.ai-analysis.index',
                    'icon' => 'bi-cpu',
                    'active' => 'admin.ai-analysis.*',
                ],
                [
                    'label' => 'Collection Logs',
                    'route' => 'admin.collection-logs.index',
                    'icon' => 'bi-clock-history',
                    'active' => 'admin.collection-logs.*',
                ],
            ],
        ],
        [
            'label' => 'System',
            'items' => [
                [
                    'label' => 'Settings',
                    'route' => 'admin.settings.index',
                    'icon' => 'bi-gear',
                    'active' => 'admin.settings.*',
                ],
            ],
        ],
    ],

];
