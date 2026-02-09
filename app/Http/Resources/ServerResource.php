<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'port' => $this->port,
            'public_address' => $this->public_address,
            'map' => $this->map,
            'last_activity' => $this->last_activity?->toIso8601String(),
            'online' => $this->last_activity && $this->last_activity->gt(Carbon::now()->subMinutes(5)),
        ];
    }
}
