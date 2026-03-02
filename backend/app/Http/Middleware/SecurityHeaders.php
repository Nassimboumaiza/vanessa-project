<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comprehensive Security Headers Middleware
 *
 * Implements OWASP-recommended security headers for modern web applications.
 * Headers are configurable via environment variables for different deployment environments.
 *
 * @see https://owasp.org/www-project-secure-headers/
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent MIME type sniffing attacks
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Prevent clickjacking attacks
        $response->headers->set('X-Frame-Options', 'DENY');

        // Note: X-XSS-Protection is deprecated in modern browsers
        // CSP provides better protection. Keeping for legacy browser support.
        $response->headers->set('X-XSS-Protection', '0');

        // Control referrer information
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Restrict browser features (formerly Feature-Policy)
        $response->headers->set('Permissions-Policy', $this->getPermissionsPolicy());

        // Content Security Policy
        $response->headers->set('Content-Security-Policy', $this->getContentSecurityPolicy());

        // HTTP Strict Transport Security (HSTS) - Production only
        if ($this->shouldEnableHsts()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Cross-Origin headers for enhanced security
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Embedder-Policy', 'require-corp');

        // Prevent caching of sensitive API responses
        if ($request->is('api/*')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }

    /**
     * Build the Content Security Policy header value.
     *
     * CSP is configured via environment variables to allow different policies
     * for development, staging, and production environments.
     */
    protected function getContentSecurityPolicy(): string
    {
        $isProduction = app()->environment('production');

        // Base directives
        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "upgrade-insecure-requests",
        ];

        // Script sources - use nonces or hashes in production
        $scriptSrc = $this->getScriptSrc($isProduction);
        $directives[] = "script-src {$scriptSrc}";

        // Style sources
        $styleSrc = $this->getStyleSrc($isProduction);
        $directives[] = "style-src {$styleSrc}";

        // Font sources
        $fontSrc = $this->getFontSrc();
        $directives[] = "font-src {$fontSrc}";

        // Image sources
        $imgSrc = $this->getImgSrc();
        $directives[] = "img-src {$imgSrc}";

        // Connect sources (API, WebSocket, etc.)
        $connectSrc = $this->getConnectSrc();
        $directives[] = "connect-src {$connectSrc}";

        // Frame sources (iframes)
        $directives[] = "frame-src 'none'";

        // Media sources
        $directives[] = "media-src 'self'";

        // Worker sources
        $directives[] = "worker-src 'self' blob:";

        // Manifest sources
        $directives[] = "manifest-src 'self'";

        // Report URI for CSP violation reporting (production only)
        if ($isProduction && config('security.csp_report_uri')) {
            $directives[] = "report-uri " . config('security.csp_report_uri');
            $directives[] = "report-to csp-endpoint";
        }

        return implode('; ', $directives);
    }

    /**
     * Get script-src directive value.
     */
    protected function getScriptSrc(bool $isProduction): string
    {
        if ($isProduction) {
            // Production: strict CSP without unsafe-inline/unsafe-eval
            // Use nonces or hashes for inline scripts
            $sources = ["'self'"];

            // Add Sentry CDN if configured
            if (config('sentry.dsn')) {
                $sources[] = 'https://browser.sentry-cdn.com';
            }

            return implode(' ', $sources);
        }

        // Development: allow more flexibility for debugging
        return "'self' 'unsafe-inline' 'unsafe-eval' blob:";
    }

    /**
     * Get style-src directive value.
     */
    protected function getStyleSrc(bool $isProduction): string
    {
        $sources = ["'self'"];

        // Allow Google Fonts
        $sources[] = 'https://fonts.googleapis.com';

        // Development allows inline styles for hot-reload
        if (!$isProduction) {
            $sources[] = "'unsafe-inline'";
        } else {
            // Production: use nonces/hashes for inline styles
            // For now, allowing inline styles due to CSS-in-JS libraries
            $sources[] = "'unsafe-inline'";
        }

        return implode(' ', $sources);
    }

    /**
     * Get font-src directive value.
     */
    protected function getFontSrc(): string
    {
        return "'self' https://fonts.gstatic.com data:";
    }

    /**
     * Get img-src directive value.
     */
    protected function getImgSrc(): string
    {
        $sources = ["'self' data:"];

        // Allow images from HTTPS sources
        $sources[] = 'https:';

        // Allow blob for dynamically generated images
        $sources[] = 'blob:';

        return implode(' ', $sources);
    }

    /**
     * Get connect-src directive value.
     */
    protected function getConnectSrc(): string
    {
        $sources = ["'self'"];

        // Add Sentry ingestion endpoint
        if (config('sentry.dsn')) {
            $sources[] = 'https://o4510969532907520.ingest.de.sentry.io';
            $sources[] = 'https://o4510969532907520.ingest.sentry.io';
        }

        // Development: allow local frontend connections
        if (app()->environment('local')) {
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $sources[] = $frontendUrl;
            $sources[] = 'http://localhost:3000';
            $sources[] = 'http://127.0.0.1:3000';
        }

        return implode(' ', $sources);
    }

    /**
     * Build the Permissions Policy header value.
     */
    protected function getPermissionsPolicy(): string
    {
        $policies = [
            'accelerometer=()',
            'ambient-light-sensor=()',
            'autoplay=()',
            'battery=()',
            'camera=()',
            'cross-origin-isolated=()',
            'display-capture=()',
            'document-domain=()',
            'encrypted-media=()',
            'execution-while-not-rendered=()',
            'execution-while-out-of-viewport=()',
            'fullscreen=(self)',
            'geolocation=()',
            'gyroscope=()',
            'keyboard-map=()',
            'magnetometer=()',
            'microphone=()',
            'midi=()',
            'navigation-override=()',
            'payment=()',
            'picture-in-picture=()',
            'publickey-credentials-get=()',
            'screen-wake-lock=()',
            'sync-xhr=()',
            'usb=()',
            'web-share=()',
            'xr-spatial-tracking=()',
        ];

        return implode(', ', $policies);
    }

    /**
     * Determine if HSTS should be enabled.
     * Only enable in production with HTTPS.
     */
    protected function shouldEnableHsts(): bool
    {
        return app()->environment('production')
            && request()->isSecure()
            && config('security.hsts_enabled', true);
    }
}
