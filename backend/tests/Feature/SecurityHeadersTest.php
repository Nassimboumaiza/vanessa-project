<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security Headers Test
 *
 * Verifies that all OWASP-recommended security headers are properly set.
 */
class SecurityHeadersTest extends TestCase
{
    /**
     * Test that all basic security headers are present.
     */
    public function test_basic_security_headers_are_present(): void
    {
        $response = $this->getJson('/api/v1/health');

        // X-Content-Type-Options prevents MIME sniffing
        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        // X-Frame-Options prevents clickjacking
        $response->assertHeader('X-Frame-Options', 'DENY');

        // Referrer-Policy controls referrer information
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /**
     * Test that X-XSS-Protection is set to 0 (deprecated, CSP is the modern approach).
     */
    public function test_xss_protection_is_disabled(): void
    {
        $response = $this->getJson('/api/v1/health');

        // X-XSS-Protection is deprecated, set to 0
        $response->assertHeader('X-XSS-Protection', '0');
    }

    /**
     * Test that Content Security Policy header is present.
     */
    public function test_content_security_policy_is_present(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertHeader('Content-Security-Policy');

        $csp = $response->headers->get('Content-Security-Policy');

        // Verify essential CSP directives
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
    }

    /**
     * Test that Permissions Policy header is present.
     */
    public function test_permissions_policy_is_present(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertHeader('Permissions-Policy');

        $policy = $response->headers->get('Permissions-Policy');

        // Verify dangerous features are disabled
        $this->assertStringContainsString('camera=()', $policy);
        $this->assertStringContainsString('microphone=()', $policy);
        $this->assertStringContainsString('geolocation=()', $policy);
        $this->assertStringContainsString('payment=()', $policy);
    }

    /**
     * Test that Cross-Origin headers are present.
     */
    public function test_cross_origin_headers_are_present(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->assertHeader('Cross-Origin-Embedder-Policy', 'require-corp');
    }

    /**
     * Test that API responses have no-cache headers.
     */
    public function test_api_responses_have_no_cache_headers(): void
    {
        $response = $this->getJson('/api/v1/health');

        $cacheControl = $response->headers->get('Cache-Control');

        // Verify no-cache directives are present (order may vary)
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);

        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader('Expires', '0');
    }

    /**
     * Test that CSP report endpoint is accessible.
     */
    public function test_csp_report_endpoint_is_accessible(): void
    {
        $report = [
            'csp-report' => [
                'document-uri' => 'http://localhost:3000/test',
                'violated-directive' => 'script-src',
                'blocked-uri' => 'http://evil.com/script.js',
            ],
        ];

        $response = $this->postJson('/api/v1/csp-report', $report);

        $response->assertStatus(204);
    }

    /**
     * Test that HSTS is not enabled in local environment.
     */
    public function test_hsts_not_enabled_in_local_environment(): void
    {
        $response = $this->getJson('/api/v1/health');

        // HSTS should not be present in local environment
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    /**
     * Test CSP does not allow unsafe-eval in production.
     */
    public function test_csp_restricts_unsafe_eval_in_production(): void
    {
        $this->app->detectEnvironment(function () {
            return 'production';
        });

        $response = $this->getJson('/api/v1/health');

        $csp = $response->headers->get('Content-Security-Policy');

        // Production CSP should not have unsafe-eval
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
    }
}
