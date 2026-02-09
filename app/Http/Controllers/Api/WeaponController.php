<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WeaponStatisticsRequest;
use App\Http\Resources\WeaponStatisticsResource;
use App\Models\Weapon;

class WeaponController extends Controller
{
    public function statistics(WeaponStatisticsRequest $request)
    {
        $validated = $request->validated();

        $weapons = Weapon::query()
            ->select('weapons.code', 'weapons.name', 'weapons.game_code')
            ->selectRaw('COUNT(event_frags.id) as kills')
            ->selectRaw('SUM(CASE WHEN event_frags.headshot = 1 THEN 1 ELSE 0 END) as headshots')
            ->leftJoin('event_frags', 'weapons.code', '=', 'event_frags.weapon_code')
            ->where('weapons.game_code', $validated['game'])
            ->where('weapons.enabled', true)
            ->groupBy('weapons.code', 'weapons.name', 'weapons.game_code')
            ->havingRaw('COUNT(event_frags.id) > 0')
            ->orderByDesc('kills')
            ->paginate($request->get('per_page', 20));

        return WeaponStatisticsResource::collection($weapons);
    }
}
