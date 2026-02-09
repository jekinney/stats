<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeaponStatisticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $headshotPercentage = $this->kills > 0
            ? round(($this->headshots / $this->kills) * 100.0, 1)
            : 0.0;

        return [
            'code' => $this->code,
            'name' => $this->name,
            'kills' => (int) $this->kills,
            'headshots' => (int) $this->headshots,
            'headshot_percentage' => $headshotPercentage,
        ];
    }
}
