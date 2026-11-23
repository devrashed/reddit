<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reddit Public JSON API
    |--------------------------------------------------------------------------
    |
    | The collector only uses Reddit's publicly accessible JSON listings.
    | Set a descriptive user agent that identifies this application.
    |
    */

    'base_url' => env('REDDIT_BASE_URL', 'https://www.reddit.com'),

    'user_agent' => env('REDDIT_USER_AGENT', 'RedditBusinessLab/1.0 (Laravel; +http://localhost)'),

    'connect_timeout' => (int) env('REDDIT_CONNECT_TIMEOUT', 5),

    'timeout' => (int) env('REDDIT_HTTP_TIMEOUT', 10),

    'retry_attempts' => (int) env('REDDIT_RETRY_ATTEMPTS', 3),

    'retry_base_delay_ms' => (int) env('REDDIT_RETRY_BASE_DELAY_MS', 1000),

    'request_delay_ms' => (int) env('REDDIT_REQUEST_DELAY_MS', 1000),

    'listing_limit' => (int) env('REDDIT_LISTING_LIMIT', 100),

    'initial_max_pages' => (int) env('REDDIT_INITIAL_MAX_PAGES', 30),

    'regular_max_pages' => (int) env('REDDIT_REGULAR_MAX_PAGES', 2),

    'comments_request_limit' => (int) env('REDDIT_COMMENTS_REQUEST_LIMIT', 500),

    'comments_request_depth' => (int) env('REDDIT_COMMENTS_REQUEST_DEPTH', 10),

    'comments_max_posts_per_run' => (int) env('REDDIT_COMMENTS_MAX_POSTS_PER_RUN', 10),

    'comments_max_attempts' => (int) env('REDDIT_COMMENTS_MAX_ATTEMPTS', 3),

    'comments_more_batches' => (int) env('REDDIT_COMMENTS_MORE_BATCHES', 2),

    'comments_more_batch_size' => 100,

    'comments_max_depth' => 20,

    'qualification' => [
        'min_score' => 5,
        'min_comments' => 5,
    ],

    'pending_recheck_max_posts' => (int) env('REDDIT_PENDING_RECHECK_MAX_POSTS', 300),

    'pending_recheck_batch_size' => (int) env('REDDIT_PENDING_RECHECK_BATCH_SIZE', 100),

    'defaults' => [
        'regular_enabled' => true,
        'regular_frequency' => 3,
        'regular_times' => ['08:00', '14:00', '20:00'],
        'http_timeout' => (int) env('REDDIT_HTTP_TIMEOUT', 10),
        'retry_attempts' => (int) env('REDDIT_RETRY_ATTEMPTS', 3),
        'historical_months' => 2,
    ],

    'lock_seconds' => 3600,

    'schedule_grace_minutes' => 10,

    'queue' => env('REDDIT_QUEUE', 'default'),

    'job_tries' => 3,

    'job_backoff' => [60, 300],

    'job_timeout' => 600,

];
