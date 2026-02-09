<?php

namespace App\Events;

use App\Models\Server;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServerStatusChangedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Server $server,
        public string $status,
        public ?int $playerCount = null,
        public ?int $maxPlayers = null
    ) {
        // Eager load relationships
        $this->server->load('game');
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("game.{$this->server->game_code}.servers"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'server.status';
    }

    public function broadcastWith(): array
    {
        $data = [
            'server' => [
                'id' => $this->server->id,
                'name' => $this->server->name,
                'address' => $this->server->address,
                'port' => $this->server->port,
                'map' => $this->server->map,
            ],
            'status' => $this->status,
        ];

        if ($this->playerCount !== null && $this->maxPlayers !== null) {
            $data['player_count'] = $this->playerCount;
            $data['max_players'] = $this->maxPlayers;
        }

        return $data;
    }
}
