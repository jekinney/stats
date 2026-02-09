<?php

namespace App\Http\Controllers;

use App\Models\EventFrag;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $stats = Cache::remember('dashboard_stats', 300, function () {
            $topPlayer = Player::orderBy('skill', 'desc')->first([
                'id', 'last_name as name', 'skill', 'kills',
            ]);

            return [
                'total_players' => Player::count(),
                'active_servers' => Server::where('enabled', true)->count(),
                'total_kills' => EventFrag::count(),
                'kills_last_hour' => EventFrag::where('event_time', '>=', now()->subHour())->count(),
                'top_player' => $topPlayer ? [
                    'id' => $topPlayer->id,
                    'name' => $topPlayer->name,
                    'skill' => $topPlayer->skill,
                    'kills' => $topPlayer->kills,
                ] : [
                    'id' => 0,
                    'name' => 'No players yet',
                    'skill' => 0,
                    'kills' => 0,
                ],
                'recent_kills' => EventFrag::with(['killer:id,last_name', 'victim:id,last_name', 'weapon:code,name'])
                    ->latest('event_time')
                    ->limit(10)
                    ->get()
                    ->map(fn ($frag) => [
                        'id' => $frag->id,
                        'killer' => $frag->killer?->last_name ?? 'Unknown',
                        'victim' => $frag->victim?->last_name ?? 'Unknown',
                        'weapon' => $frag->weapon?->name ?? 'Unknown',
                        'headshot' => $frag->headshot,
                    ])->toArray(),
            ];
        });

        return Inertia::render('Dashboard', [
            'stats' => $stats,
        ]);
    }
}
