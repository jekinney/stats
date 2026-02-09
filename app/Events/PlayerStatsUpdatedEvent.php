<?php

namespace App\Events;

use App\Models\Player;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerStatsUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Player $player,
        public ?int $previousRank = null,
        public ?int $currentRank = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("player.{$this->player->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'stats.updated';
    }

    public function broadcastWith(): array
    {
        $data = [
            'player' => [
                'id' => $this->player->id,
                'name' => $this->player->last_name,
                'skill' => (float) $this->player->skill,
                'kills' => $this->player->kills,
                'deaths' => $this->player->deaths,
                'kd_ratio' => $this->player->kd_ratio,
            ],
        ];

        if ($this->previousRank !== null && $this->currentRank !== null) {
            $data['rank_change'] = [
                'previous' => $this->previousRank,
                'current' => $this->currentRank,
            ];
        }

        return $data;
    }
}
