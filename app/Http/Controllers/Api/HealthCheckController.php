<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthCheckController extends Controller
{
    public function index(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
        ];

        $status = $this->determineOverallStatus($checks);

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ]);
    }

    private function checkDatabase(): array
    {
        try {
            $startTime = microtime(true);
            DB::connection()->getPdo();
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'healthy',
                'response_time' => $responseTime.'ms',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $testKey = 'health_check_'.time();
            Cache::put($testKey, true, 10);
            $canWrite = Cache::get($testKey) === true;
            Cache::forget($testKey);

            return [
                'status' => $canWrite ? 'healthy' : 'unhealthy',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkQueue(): array
    {
        try {
            return [
                'status' => 'healthy',
                'driver' => config('queue.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function determineOverallStatus(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        if (in_array('unhealthy', $statuses)) {
            return 'unhealthy';
        }

        if (in_array('degraded', $statuses)) {
            return 'degraded';
        }

        return 'healthy';
    }
}
