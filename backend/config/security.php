<?php

declare(strict_types=1);

/**
 * Security Configuration
 *
 * This file contains security-related configuration options for the application.
 * These values can be overridden via environment variables for different deployment environments.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | HTTP Strict Transport Security (HSTS)
    |--------------------------------------------------------------------------
    |
    | Enable HSTS to force HTTPS connections. This should only be enabled in
    | production with a valid SSL certificate. The max-age is set to 1 year
    | (31536000 seconds) by default.
    |
    | WARNING: Enabling HSTS with preload can have long-lasting effects. Make sure
    | you understand the implications before enabling preload.
    |
    */

    'hsts_enabled' => (bool) env('HSTS_ENABLED', false),

    'hsts_max_age' => (int) env('HSTS_MAX_AGE', 31536000),

    'hsts_include_subdomains' => (bool) env('HSTS_INCLUDE_SUBDOMAINS', true),

    'hsts_preload' => (bool) env('HSTS_PRELOAD', false),

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy (CSP) Report URI
    |--------------------------------------------------------------------------
    |
    | The URI where CSP violation reports will be sent. This can be an internal
    | endpoint or an external service like Report URI (https://report-uri.com/).
    |
    | Set to null to disable CSP reporting.
    |
    | For internal reporting, use: /api/v1/csp-report
    |
    */

    'csp_report_uri' => env('CSP_REPORT_URI', '/api/v1/csp-report'),

    /*
    |--------------------------------------------------------------------------
    | Frame Ancestors
    |--------------------------------------------------------------------------
    |
    | Control which domains can embed this application in an iframe.
    | 'DENY' prevents all framing, 'SAMEORIGIN' allows same-origin framing.
    |
    | For API-only applications, 'DENY' is recommended.
    |
    */

    'frame_ancestors' => env('FRAME_ANCESTORS', 'DENY'),

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Configure trusted proxies for proper IP detection and HTTPS detection.
    | This is critical for security headers like HSTS to work correctly behind
    | load balancers and reverse proxies.
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES', '**'),

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | These settings control CORS behavior. Detailed CORS configuration is in
    | config/cors.php. These are additional security-related CORS settings.
    |
    */

    'cors_max_age' => (int) env('CORS_MAX_AGE', 86400),

    /*
    |--------------------------------------------------------------------------
    | Security Headers Mode
    |--------------------------------------------------------------------------
    |
    | Set to 'strict' for maximum security (may break some functionality).
    | Set to 'moderate' for balanced security (recommended for most apps).
    | Set to 'relaxed' for development or legacy applications.
    |
    */

    'headers_mode' => env('SECURITY_HEADERS_MODE', 'moderate'),

];
