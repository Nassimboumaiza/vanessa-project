<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Security Controller
 *
 * Handles security-related endpoints such as CSP violation reporting.
 */
class SecurityController
{
    /**
     * Handle CSP violation reports.
     *
     * This endpoint receives Content Security Policy violation reports
     * sent by browsers when a CSP directive is violated.
     *
     * @param  Request  $request
     */
    public function cspReport(Request $request): JsonResponse
    {
        $report = $request->json()->all();

        // Log the CSP violation for monitoring and analysis
        Log::channel('single')->warning('CSP Violation Detected', [
            'document_uri' => $report['csp-report']['document-uri'] ?? 'unknown',
            'violated_directive' => $report['csp-report']['violated-directive'] ?? 'unknown',
            'blocked_uri' => $report['csp-report']['blocked-uri'] ?? 'unknown',
            'source_file' => $report['csp-report']['source-file'] ?? 'unknown',
            'line_number' => $report['csp-report']['line-number'] ?? null,
            'column_number' => $report['csp-report']['column-number'] ?? null,
            'original_policy' => $report['csp-report']['original-policy'] ?? 'unknown',
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'timestamp' => now()->toIso8601String(),
        ]);

        // Return empty response - browsers expect 204 No Content for CSP reports
        return response()->json(null, 204);
    }
}
