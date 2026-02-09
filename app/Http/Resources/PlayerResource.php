<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->last_name,
            'skill' => (float) $this->skill,
            'kills' => $this->kills,
            'deaths' => $this->deaths,
            'kd_ratio' => $this->kd_ratio,
            'headshots' => $this->headshots,
            'headshot_percentage' => $this->when(
                isset($this->headshot_count),
                fn () => $this->kills_count > 0
                    ? round(($this->headshot_count / $this->kills_count) * 100.0, 1)
                    : 0.0
            ),
            'recent_kills' => $this->when(
                $this->relationLoaded('kills'),
                function () {
                    return $this->getRelation('kills')->map(function ($frag) {
                        return [
                            'victim' => $frag->victim?->last_name,
                            'weapon' => $frag->weapon?->code,
                            'headshot' => $frag->headshot,
                            'map' => $frag->map,
                            'time' => $frag->event_time?->toIso8601String(),
                        ];
                    });
                }
            ),
            'weapon_stats' => $this->when(
                $request->routeIs('api.players.show'),
                function () {
                    return $this->kills()
                        ->selectRaw('weapon_code as weapon, count(*) as kills')
                        ->groupBy('weapon_code')
                        ->orderByDesc('kills')
                        ->get()
                        ->map(fn ($stat) => [
                            'weapon' => $stat->weapon,
                            'kills' => $stat->kills,
                        ]);
                }
            ),
            'last_event' => $this->last_event?->toIso8601String(),
        ];
    }
}
