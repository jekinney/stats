<?php

namespace App\Jobs;

use App\Events\KillFeedEvent;
use App\Models\EventFrag;
use App\Models\Player;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessLogEvent implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private array $eventData
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Only process kill events
        if ($this->eventData['type'] !== 'kill') {
            return;
        }

        // Load server to get game_code for player creation
        $server = \App\Models\Server::findOrFail($this->eventData['server_id']);

        // Prepare killer creation attributes
        $killerAttributes = ['game_code' => $server->game_code];
        if (isset($this->eventData['killer']['name'])) {
            $killerAttributes['last_name'] = $this->eventData['killer']['name'];
        }

        // Find or create killer player
        $killer = Player::firstOrCreate(
            ['steam_id' => $this->eventData['killer']['steam_id']],
            $killerAttributes
        );

        // Prepare victim creation attributes
        $victimAttributes = ['game_code' => $server->game_code];
        if (isset($this->eventData['victim']['name'])) {
            $victimAttributes['last_name'] = $this->eventData['victim']['name'];
        }

        // Find or create victim player
        $victim = Player::firstOrCreate(
            ['steam_id' => $this->eventData['victim']['steam_id']],
            $victimAttributes
        );

        // Extract killer position coordinates (optional)
        $killerPosition = $this->eventData['killer']['position'] ?? null;

        // Create event frag record
        $eventFrag = EventFrag::create([
            'server_id' => $this->eventData['server_id'],
            'killer_id' => $killer->id,
            'victim_id' => $victim->id,
            'weapon_code' => $this->eventData['weapon'],
            'headshot' => $this->eventData['headshot'],
            'map' => $server->map,
            'pos_x' => $killerPosition[0] ?? null,
            'pos_y' => $killerPosition[1] ?? null,
            'pos_z' => $killerPosition[2] ?? null,
            'event_time' => $this->eventData['timestamp'],
        ]);

        // Update player statistics
        $killer->increment('kills');
        $victim->increment('deaths');

        // Broadcast kill feed event
        event(new KillFeedEvent($eventFrag));
    }
}
