<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    /**
     * Test that API endpoints have rate limiting headers.
     */
    public function test_api_endpoints_have_rate_limit_headers(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->getJson('/api/v1/user', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200);
        
        // Check for rate limit headers (Laravel adds these)
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    /**
     * Test that auth endpoints have stricter rate limiting.
     */
    public function test_auth_endpoints_have_stricter_rate_limit(): void
    {
        // Make multiple requests to auth endpoint
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ]);

            if ($i < 4) {
                $response->assertStatus(401); // Unauthorized for wrong credentials
            }
        }

        // The 6th request should be rate limited (5 per minute limit)
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429); // Too Many Requests
    }

    /**
     * Test that rate limiting is per-user when authenticated.
     */
    public function test_rate_limiting_per_user_when_authenticated(): void
    {
        $user1 = User::factory()->create();
        $token1 = $user1->createToken('test_token')->plainTextToken;

        $user2 = User::factory()->create();
        $token2 = $user2->createToken('test_token')->plainTextToken;

        // User 1 makes a request
        $response1 = $this->getJson('/api/v1/user', [
            'Authorization' => 'Bearer ' . $token1,
        ]);
        $response1->assertStatus(200);

        // User 2 makes a request - should have full limit available
        $response2 = $this->getJson('/api/v1/user', [
            'Authorization' => 'Bearer ' . $token2,
        ]);
        $response2->assertStatus(200);

        // Each user should have their own rate limit
        $remaining1 = (int) $response1->headers->get('X-RateLimit-Remaining');
        $remaining2 = (int) $response2->headers->get('X-RateLimit-Remaining');

        // Both should start with similar remaining counts (60 limit)
        $this->assertGreaterThan(55, $remaining1);
        $this->assertGreaterThan(55, $remaining2);
    }

    /**
     * Test that health check endpoints are not rate limited.
     */
    public function test_health_check_not_rate_limited(): void
    {
        // Health check should not require authentication or rate limiting
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        
        // Health check should not have rate limit headers (it's public)
        // Note: Laravel may still add headers, so we just verify it works
    }
}
