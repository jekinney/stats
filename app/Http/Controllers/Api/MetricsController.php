<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventFrag;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Http\JsonResponse;

class MetricsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'timestamp' => now()->toIso8601String(),
            'database' => [
                'total_players' => Player::count(),
                'total_frags' => EventFrag::count(),
                'total_servers' => Server::count(),
            ],
            'performance' => [
                'avg_response_time' => 0,
                'requests_per_minute' => 0,
            ],
        ]);
    }
}
