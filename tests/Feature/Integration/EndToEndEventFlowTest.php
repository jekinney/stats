<?php

declare(strict_types=1);

use App\Jobs\ProcessLogEvent;
use App\Models\EventFrag;
use App\Models\Player;
use App\Models\Server;
use App\Models\Weapon;
use App\Services\LogParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create base test data
    $this->server = Server::factory()->create([
        'address' => '192.168.1.1:27015',
        'name' => 'Test Server',
        'game_code' => 'csgo',
    ]);

    $this->weapon = Weapon::factory()->create([
        'code' => 'ak47',
        'name' => 'AK-47',
    ]);

    $this->killer = Player::factory()->create([
        'steamid' => 'STEAM_1:0:12345',
        'name' => 'Killer',
        'skill' => 1000,
    ]);

    $this->victim = Player::factory()->create([
        'steamid' => 'STEAM_1:0:67890',
        'name' => 'Victim',
        'skill' => 1000,
    ]);
});

test('processes complete frag event flow from log to database', function () {
    $logLine = 'L 02/09/2026 - 12:34:56: "Killer<1><STEAM_1:0:12345><TERRORIST>" killed "Victim<2><STEAM_1:0:67890><CT>" with "ak47" (headshot)';

    $parser = new LogParser();
    $event = $parser->parseKillEvent($logLine);

    expect($event)->toBeArray()
        ->and($event['type'])->toBe('kill')
        ->and($event['killer_steamid'])->toBe('STEAM_1:0:12345')
        ->and($event['victim_steamid'])->toBe('STEAM_1:0:67890')
        ->and($event['weapon'])->toBe('ak47')
        ->and($event['headshot'])->toBeTrue();

    ProcessLogEvent::dispatch($event, $this->server->id);

    Queue::assertPushed(ProcessLogEvent::class);
});

test('creates frag record with all relationships', function () {
    $event = [
        'type' => 'kill',
        'killer_steamid' => 'STEAM_1:0:12345',
        'victim_steamid' => 'STEAM_1:0:67890',
        'weapon' => 'ak47',
        'headshot' => true,
        'map' => 'de_dust2',
        'timestamp' => now(),
    ];

    ProcessLogEvent::dispatchSync($event, $this->server->id);

    $frag = EventFrag::latest()->first();

    expect($frag)->not->toBeNull()
        ->and($frag->killer_id)->toBe($this->killer->id)
        ->and($frag->victim_id)->toBe($this->victim->id)
        ->and($frag->weapon_code)->toBe($this->weapon->code)
        ->and($frag->server_id)->toBe($this->server->id)
        ->and($frag->headshot)->toBeTrue();
});

test('updates player statistics after frag event', function () {
    $initialKillerKills = $this->killer->kills;
    $initialVictimDeaths = $this->victim->deaths;

    $event = [
        'type' => 'kill',
        'killer_steamid' => 'STEAM_1:0:12345',
        'victim_steamid' => 'STEAM_1:0:67890',
        'weapon' => 'ak47',
        'headshot' => false,
        'map' => 'de_dust2',
        'timestamp' => now(),
    ];

    ProcessLogEvent::dispatchSync($event, $this->server->id);

    $this->killer->refresh();
    $this->victim->refresh();

    expect($this->killer->kills)->toBe($initialKillerKills + 1)
        ->and($this->victim->deaths)->toBe($initialVictimDeaths + 1);
});

test('processes multiple sequential frag events', function () {
    $events = [
        [
            'type' => 'kill',
            'killer_steamid' => 'STEAM_1:0:12345',
            'victim_steamid' => 'STEAM_1:0:67890',
            'weapon' => 'ak47',
            'headshot' => true,
            'map' => 'de_dust2',
            'timestamp' => now(),
        ],
        [
            'type' => 'kill',
            'killer_steamid' => 'STEAM_1:0:67890',
            'victim_steamid' => 'STEAM_1:0:12345',
            'weapon' => 'ak47',
            'headshot' => false,
            'map' => 'de_dust2',
            'timestamp' => now()->addSecond(),
        ],
        [
            'type' => 'kill',
            'killer_steamid' => 'STEAM_1:0:12345',
            'victim_steamid' => 'STEAM_1:0:67890',
            'weapon' => 'ak47',
            'headshot' => true,
            'map' => 'de_dust2',
            'timestamp' => now()->addSeconds(2),
        ],
    ];

    foreach ($events as $event) {
        ProcessLogEvent::dispatchSync($event, $this->server->id);
    }

    expect(EventFrag::count())->toBe(3);

    $this->killer->refresh();
    $this->victim->refresh();

    expect($this->killer->kills)->toBe(2)
        ->and($this->victim->kills)->toBe(1)
        ->and($this->killer->deaths)->toBe(1)
        ->and($this->victim->deaths)->toBe(2);
});

test('retrieves frag events through API endpoint', function () {
    EventFrag::factory()->count(5)->create([
        'killer_id' => $this->killer->id,
        'victim_id' => $this->victim->id,
        'weapon_code' => $this->weapon->code,
        'server_id' => $this->server->id,
    ]);

    $response = $this->getJson('/api/frags');

    $response->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'killer',
                    'victim',
                    'weapon',
                    'server',
                    'headshot',
                    'event_time',
                ],
            ],
        ]);
});

test('end-to-end flow updates player rankings', function () {
    $event = [
        'type' => 'kill',
        'killer_steamid' => 'STEAM_1:0:12345',
        'victim_steamid' => 'STEAM_1:0:67890',
        'weapon' => 'ak47',
        'headshot' => true,
        'map' => 'de_dust2',
        'timestamp' => now(),
    ];

    ProcessLogEvent::dispatchSync($event, $this->server->id);

    $response = $this->getJson('/api/players/rankings');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'skill',
                    'kills',
                    'deaths',
                ],
            ],
        ]);

    $rankings = $response->json('data');
    $killerRanking = collect($rankings)->firstWhere('id', $this->killer->id);

    expect($killerRanking['kills'])->toBeGreaterThan(0);
});

test('handles missing player gracefully by creating them', function () {
    $newSteamId = 'STEAM_1:0:99999';

    $event = [
        'type' => 'kill',
        'killer_steamid' => $newSteamId,
        'victim_steamid' => 'STEAM_1:0:67890',
        'weapon' => 'ak47',
        'headshot' => false,
        'map' => 'de_dust2',
        'timestamp' => now(),
    ];

    expect(Player::where('steamid', $newSteamId)->exists())->toBeFalse();

    ProcessLogEvent::dispatchSync($event, $this->server->id);

    expect(Player::where('steamid', $newSteamId)->exists())->toBeTrue();
    expect(EventFrag::count())->toBe(1);
});

test('handles missing weapon gracefully', function () {
    $newWeaponCode = 'new_weapon';

    $event = [
        'type' => 'kill',
        'killer_steamid' => 'STEAM_1:0:12345',
        'victim_steamid' => 'STEAM_1:0:67890',
        'weapon' => $newWeaponCode,
        'headshot' => false,
        'map' => 'de_dust2',
        'timestamp' => now(),
    ];

    expect(Weapon::where('code', $newWeaponCode)->exists())->toBeFalse();

    ProcessLogEvent::dispatchSync($event, $this->server->id);

    expect(Weapon::where('code', $newWeaponCode)->exists())->toBeTrue();
    expect(EventFrag::count())->toBe(1);
});

test('processes events maintaining data consistency', function () {
    $events = [];
    for ($i = 0; $i < 10; $i++) {
        $events[] = [
            'type' => 'kill',
            'killer_steamid' => 'STEAM_1:0:12345',
            'victim_steamid' => 'STEAM_1:0:67890',
            'weapon' => 'ak47',
            'headshot' => $i % 2 === 0,
            'map' => 'de_dust2',
            'timestamp' => now()->addSeconds($i),
        ];
    }

    foreach ($events as $event) {
        ProcessLogEvent::dispatchSync($event, $this->server->id);
    }

    $this->killer->refresh();

    expect($this->killer->kills)->toBe(10)
        ->and($this->killer->headshots)->toBe(5)
        ->and(EventFrag::count())->toBe(10);

    $headshotFrags = EventFrag::where('headshot', true)->count();
    expect($headshotFrags)->toBe(5);
});
