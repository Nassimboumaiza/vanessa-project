<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

        // Security Headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // Content Security Policy (CSP) - adjust as needed
        $csp = "default-src 'self'; ".
               "script-src 'self' 'unsafe-inline' 'unsafe-eval'; ".
               "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; ".
               "font-src 'self' https://fonts.gstatic.com; ".
               "img-src 'self' data: https:; ".
               "connect-src 'self' http://localhost:3000 http://127.0.0.1:3000; ".
               "frame-ancestors 'none'; ".
               "base-uri 'self'; ".
               "form-action 'self';";

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
