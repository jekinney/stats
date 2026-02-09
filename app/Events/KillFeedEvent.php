<?php

namespace App\Events;

use App\Models\EventFrag;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KillFeedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public EventFrag $frag)
    {
        // Eager load relationships to avoid N+1 queries
        $this->frag->load(['killer', 'victim', 'server']);
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("game.{$this->frag->server->game_code}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'kill.feed';
    }

    public function broadcastWith(): array
    {
        return [
            'killer' => [
                'id' => $this->frag->killer_id,
                'name' => $this->frag->killer->last_name,
            ],
            'victim' => [
                'id' => $this->frag->victim_id,
                'name' => $this->frag->victim->last_name,
            ],
            'weapon' => $this->frag->weapon_code,
            'headshot' => $this->frag->headshot,
            'timestamp' => $this->frag->event_time,
        ];
    }
}
