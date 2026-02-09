<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventFragResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'killer' => $this->when(
                $this->relationLoaded('killer') && $this->killer,
                fn () => [
                    'id' => $this->killer->id,
                    'name' => $this->killer->last_name,
                    'skill' => $this->killer->skill,
                ]
            ),
            'victim' => $this->when(
                $this->relationLoaded('victim') && $this->victim,
                fn () => [
                    'id' => $this->victim->id,
                    'name' => $this->victim->last_name,
                    'skill' => $this->victim->skill,
                ]
            ),
            'weapon' => $this->weapon_code,
            'weapon_name' => $this->when(
                $this->relationLoaded('weapon') && $this->weapon,
                fn () => $this->weapon->name
            ),
            'server' => $this->when(
                $this->relationLoaded('server') && $this->server,
                fn () => [
                    'id' => $this->server->id,
                    'name' => $this->server->name,
                    'address' => $this->server->address,
                ]
            ),
            'headshot' => (bool) $this->headshot,
            'map' => $this->map,
            'event_time' => $this->event_time?->toIso8601String(),
        ];
    }
}
