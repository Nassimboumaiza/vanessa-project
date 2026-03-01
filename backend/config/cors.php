<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    | SECURITY NOTE: Never use wildcard (*) for allowed_origins in production
    | when supports_credentials is true. This is invalid per CORS spec and
    | creates a security vulnerability.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Methods
    |--------------------------------------------------------------------------
    |
    | Specifies which HTTP methods are allowed. In production, limit to only
    | the methods your API actually uses. Using ['*'] is acceptable only if
    | you understand the security implications.
    |
    */
    'allowed_methods' => explode(',', env('CORS_ALLOWED_METHODS', 'GET,POST,PUT,PATCH,DELETE,OPTIONS')),

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | SECURITY CRITICAL: Must be explicit domains in production.
    | Never use ['*'] when supports_credentials is true.
    |
    | Environment Variables:
    | - CORS_ALLOWED_ORIGINS: Comma-separated list for production
    | - CORS_ALLOWED_ORIGINS_DEV: Development fallback (defaults to localhost)
    |
    */
    'allowed_origins' => explode(',', env(
        'CORS_ALLOWED_ORIGINS',
        env('APP_ENV') === 'production'
            ? '' // FAIL SAFE: Empty in production if not configured
            : env('CORS_ALLOWED_ORIGINS_DEV', 'http://localhost:3000,http://localhost:3001,http://127.0.0.1:3000,http://127.0.0.1:3001')
    )),

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins Patterns
    |--------------------------------------------------------------------------
    |
    | Regex patterns for dynamic origin matching. Use sparingly and only
    | when absolutely necessary (e.g., multi-tenant subdomains).
    |
    | Example: ['/\.example\.com$/'] to allow all subdomains
    |
    */
    'allowed_origins_patterns' => [],

    /*
    |--------------------------------------------------------------------------
    | Allowed Headers
    |--------------------------------------------------------------------------
    |
    | Headers that the client can use in the request. Limit to only what
    | your API requires. Avoid ['*'] in production.
    |
    */
    'allowed_headers' => explode(',', env(
        'CORS_ALLOWED_HEADERS',
        'Content-Type,Authorization,X-Requested-With,X-CSRF-TOKEN,Accept,Origin,X-XSRF-TOKEN'
    )),

    /*
    |--------------------------------------------------------------------------
    | Exposed Headers
    |--------------------------------------------------------------------------
    |
    | Headers that the client can access in the response. Add any custom
    | headers your frontend needs to read.
    |
    */
    'exposed_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Max Age
    |--------------------------------------------------------------------------
    |
    | Duration in seconds that preflight request results can be cached.
    | 1728000 = 20 days (reasonable for stable APIs).
    |
    */
    'max_age' => (int) env('CORS_MAX_AGE', 1728000),

    /*
    |--------------------------------------------------------------------------
    | Supports Credentials
    |--------------------------------------------------------------------------
    |
    | REQUIRED for Sanctum cookie-based SPA authentication.
    | When true, allowed_origins MUST NOT contain '*' (wildcard).
    | This enables cookies, authorization headers, and TLS client certificates.
    |
    | SECURITY: Only set to false if you don't need cookie-based auth.
    |
    */
    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', true),

];
