<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration values specific to the API layer of the application.
    | All values should be defined in the .env file and accessed via
    | the config() helper for proper environment management.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | The number of requests allowed per minute per user/IP.
    | This helps prevent API abuse and ensures fair usage.
    |
    */
    'rate_limit_per_minute' => (int) env('RATE_LIMIT_PER_MINUTE', 60),

    /*
    |--------------------------------------------------------------------------
    | Auth Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Stricter rate limits for authentication endpoints to prevent brute force.
    |
    */
    'auth_rate_limit' => [
        'max_attempts' => (int) env('AUTH_RATE_LIMIT_ATTEMPTS', 5),
        'decay_minutes' => (int) env('AUTH_RATE_LIMIT_DECAY', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Version
    |--------------------------------------------------------------------------
    |
    | Current API version for versioning endpoints.
    |
    */
    'version' => env('API_VERSION', 'v1'),

    /*
    |--------------------------------------------------------------------------
    | Pagination Defaults
    |--------------------------------------------------------------------------
    |
    | Default values for API pagination.
    |
    */
    'pagination' => [
        'default_per_page' => (int) env('API_DEFAULT_PER_PAGE', 15),
        'max_per_page' => (int) env('API_MAX_PER_PAGE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the health check endpoint.
    |
    */
    'health_check' => [
        'enabled' => (bool) env('HEALTH_CHECK_ENABLED', true),
        'timeout_seconds' => (int) env('HEALTH_CHECK_TIMEOUT', 5),
        'include_details' => (bool) env('HEALTH_CHECK_DETAILS', false),
    ],
];
