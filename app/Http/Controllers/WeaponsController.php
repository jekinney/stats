<?php

namespace App\Http\Controllers;

use App\Models\Weapon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WeaponsController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Weapon::query();

        if ($request->has('game')) {
            $query->where('game_code', $request->get('game'));
        }

        $weapons = $query->withCount('eventFrags as total_kills')
            ->get()
            ->map(function ($weapon) {
                $headshots = $weapon->eventFrags()->where('headshot', true)->count();
                $totalKills = $weapon->total_kills ?? 0;

                return [
                    'code' => $weapon->code,
                    'name' => $weapon->name,
                    'total_kills' => $totalKills,
                    'headshots' => $headshots,
                    'accuracy' => $totalKills > 0 ? round(($headshots / $totalKills) * 100, 1) : 0,
                ];
            })
            ->sortByDesc('total_kills')
            ->values();

        return Inertia::render('Weapons/Index', [
            'weapons' => $weapons,
        ]);
    }
}
