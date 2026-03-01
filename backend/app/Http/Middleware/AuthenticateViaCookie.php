<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate via auth_token cookie
 *
 * This middleware reads the auth_token cookie and authenticates
 * the user using Sanctum's token-based authentication.
 * This enables SPA-style cookie authentication for the frontend.
 */
class AuthenticateViaCookie
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if already authenticated
        if (Auth::check()) {
            return $next($request);
        }

        // First, try Bearer token authentication (for API clients and tests)
        $bearerToken = $request->bearerToken();
        if ($bearerToken) {
            $accessToken = PersonalAccessToken::findToken($bearerToken);

            if ($accessToken) {
                $user = $accessToken->tokenable;

                if ($user && $user->is_active) {
                    Auth::guard('sanctum')->setUser($user);
                    Auth::setUser($user);
                    $request->setUserResolver(fn () => $user);
                }
            }

            return $next($request);
        }

        // Try to get token from auth_token cookie (for SPA)
        $token = $request->cookie('auth_token');

        if (! $token) {
            return $next($request);
        }

        // Find the token in Sanctum's personal access tokens
        $accessToken = PersonalAccessToken::findToken($token);

        if (! $accessToken) {
            return $next($request);
        }

        $user = $accessToken->tokenable;

        if (! $user) {
            return $next($request);
        }

        if (! $user->is_active) {
            return $next($request);
        }

        // Authenticate using Sanctum guard
        Auth::guard('sanctum')->setUser($user);
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        // Set Authorization header for subsequent auth:sanctum middleware
        $request->headers->set('Authorization', 'Bearer ' . $token);

        return $next($request);
    }
}
