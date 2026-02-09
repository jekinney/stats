<?php

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlayersController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Player::query();

        if ($request->has('game')) {
            $query->where('game_code', $request->get('game'));
        }

        $players = $query->orderBy('skill', 'desc')
            ->paginate($request->get('per_page', 20))
            ->through(fn ($player) => [
                'id' => $player->id,
                'name' => $player->last_name,
                'skill' => $player->skill,
                'kills' => $player->kills,
                'deaths' => $player->deaths,
                'kd_ratio' => $player->deaths > 0 ? round($player->kills / $player->deaths, 2) : $player->kills,
            ]);

        return Inertia::render('Players/Index', [
            'players' => $players,
        ]);
    }

    public function show(Player $player): Response
    {
        // Store the kills count before loading relationship
        $killsCount = $player->kills;
        $deathsCount = $player->deaths;

        $player->load(['kills' => function ($query) {
            $query->with(['victim:id,last_name', 'weapon:code,name'])
                ->latest('event_time')
                ->limit(50);
        }]);

        return Inertia::render('Players/Show', [
            'player' => [
                'id' => $player->id,
                'name' => $player->last_name,
                'skill' => $player->skill,
                'kills' => $killsCount,
                'deaths' => $deathsCount,
                'kd_ratio' => $deathsCount > 0 ? round($killsCount / $deathsCount, 2) : $killsCount,
                'game_code' => $player->game_code,
                'recent_frags' => $player->kills->map(fn ($frag) => [
                    'victim' => $frag->victim?->last_name ?? 'Unknown',
                    'weapon' => $frag->weapon?->name ?? 'Unknown',
                    'headshot' => $frag->headshot,
                    'time' => $frag->event_time,
                ]),
            ],
        ]);
    }
}
