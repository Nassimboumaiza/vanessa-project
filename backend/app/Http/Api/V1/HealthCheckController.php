<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class HealthCheckController extends BaseController
{
    /**
     * Perform a comprehensive health check of the application.
     *
     * Checks database connectivity and returns structured status information.
     */
    public function check(): JsonResponse
    {
        if (! config('api.health_check.enabled', true)) {
            return $this->errorResponse(
                'Health check endpoint is disabled',
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        $checks = [
            'database' => $this->checkDatabase(),
        ];

        $allHealthy = collect($checks)->every(fn ($check) => $check['status'] === 'healthy');

        $responseData = [
            'status' => $allHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ];

        if (config('api.health_check.include_details', false)) {
            $responseData['environment'] = config('app.env');
            $responseData['version'] = config('api.version', 'v1');
        }

        $statusCode = $allHealthy
            ? Response::HTTP_OK
            : Response::HTTP_SERVICE_UNAVAILABLE;

        return response()->json($responseData, $statusCode);
    }

    /**
     * Check database connectivity.
     *
     * @return array<string, mixed>
     */
    private function checkDatabase(): array
    {
        $timeout = config('api.health_check.timeout_seconds', 5);

        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');

            return [
                'status' => 'healthy',
                'message' => 'Database connection is working',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed',
            ];
        }
    }

    /**
     * Simple ping endpoint for load balancers.
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ], Response::HTTP_OK);
    }
}
