<?php

namespace App\Jobs;

use App\Events\KillFeedEvent;
use App\Models\EventFrag;
use App\Models\Player;
use App\Models\Weapon;
use App\Services\SkillCalculator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessLogEvent implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private array $eventData,
        private ?int $serverId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SkillCalculator $skillCalculator): void
    {
        // Only process kill events
        if ($this->eventData['type'] !== 'kill') {
            return;
        }

        // Get server_id from constructor parameter or event data
        $serverId = $this->serverId ?? $this->eventData['server_id'];
        $server = \App\Models\Server::findOrFail($serverId);

        // Extract killer and victim steam IDs (support both flat and nested structures)
        $killerSteamId = $this->eventData['killer']['steam_id'] ?? $this->eventData['killer_steamid'];
        $victimSteamId = $this->eventData['victim']['steam_id'] ?? $this->eventData['victim_steamid'];
        $killerName = $this->eventData['killer']['name'] ?? $this->eventData['killer_name'] ?? "Player_{$killerSteamId}";
        $victimName = $this->eventData['victim']['name'] ?? $this->eventData['victim_name'] ?? "Player_{$victimSteamId}";

        // Find or create killer player
        $killer = Player::firstOrCreate(
            ['steam_id' => $killerSteamId],
            [
                'game_code' => $server->game_code,
                'last_name' => $killerName,
            ]
        );
        $killer->refresh(); // Ensure skill default value is loaded

        // Find or create victim player
        $victim = Player::firstOrCreate(
            ['steam_id' => $victimSteamId],
            [
                'game_code' => $server->game_code,
                'last_name' => $victimName,
            ]
        );
        $victim->refresh(); // Ensure skill default value is loaded

        // Load or create weapon
        $weaponCode = $this->eventData['weapon'];
        $weapon = Weapon::firstOrCreate(
            ['code' => $weaponCode],
            [
                'game_code' => $server->game_code,
                'name' => ucfirst($weaponCode),
            ]
        );

        // Extract killer position coordinates (optional)
        $killerPosition = $this->eventData['killer']['position'] ?? null;

        // Get map from event data or server
        $map = $this->eventData['map'] ?? $server->map;

        // Create event frag record
        $eventFrag = EventFrag::create([
            'server_id' => $serverId,
            'killer_id' => $killer->id,
            'victim_id' => $victim->id,
            'weapon_code' => $weaponCode,
            'headshot' => $this->eventData['headshot'],
            'map' => $map,
            'pos_x' => $killerPosition[0] ?? null,
            'pos_y' => $killerPosition[1] ?? null,
            'pos_z' => $killerPosition[2] ?? null,
            'event_time' => $this->eventData['timestamp'],
        ]);

        // Update player statistics
        $killer->increment('kills');
        if ($this->eventData['headshot']) {
            $killer->increment('headshots');
        }
        $victim->increment('deaths');

        // Calculate and update skill ratings
        $newKillerSkill = $skillCalculator->calculateKillSkill(
            $killer,
            $victim,
            $weapon,
            $this->eventData['headshot']
        );
        $newVictimSkill = $skillCalculator->calculateDeathSkill($victim, $killer);

        $killer->update(['skill' => $newKillerSkill]);
        $victim->update(['skill' => $newVictimSkill]);

        // Broadcast kill feed event
        event(new KillFeedEvent($eventFrag));
    }
}
