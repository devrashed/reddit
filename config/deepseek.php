<?php

return [

    /*
    |--------------------------------------------------------------------------
    | DeepSeek API
    |--------------------------------------------------------------------------
    |
    | Credentials are read from the server environment only. They must never
    | be sent to the browser or included in frontend assets.
    |
    */

    'api_key' => env('DEEPSEEK_API_KEY'),

    'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),

    'path' => env('DEEPSEEK_PATH', '/chat/completions'),

    'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),

    'connect_timeout' => (int) env('DEEPSEEK_CONNECT_TIMEOUT', 10),

    'timeout' => (int) env('DEEPSEEK_TIMEOUT', 60),

    'retry_attempts' => (int) env('DEEPSEEK_RETRY_ATTEMPTS', 2),

    'retry_base_delay_ms' => (int) env('DEEPSEEK_RETRY_BASE_DELAY_MS', 1000),

    'temperature' => (float) env('DEEPSEEK_TEMPERATURE', 0.2),

    'max_output_tokens' => (int) env('DEEPSEEK_MAX_OUTPUT_TOKENS', 2500),

    'json_mode' => (bool) env('DEEPSEEK_JSON_MODE', true),

    'post_char_limit' => (int) env('DEEPSEEK_POST_CHAR_LIMIT', 8000),

    'comments_limit' => (int) env('DEEPSEEK_COMMENTS_LIMIT', 40),

    'comment_char_limit' => (int) env('DEEPSEEK_COMMENT_CHAR_LIMIT', 1200),

    'prompt_char_limit' => (int) env('DEEPSEEK_PROMPT_CHAR_LIMIT', 24000),

    'stale_processing_minutes' => (int) env('DEEPSEEK_STALE_PROCESSING_MINUTES', 10),

    'lock_seconds' => (int) env('DEEPSEEK_LOCK_SECONDS', 300),

    'analysis_type' => 'business_opportunity',

];
