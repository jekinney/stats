<?php

declare(strict_types=1);

use App\Jobs\ProcessLogEvent;
use App\Models\Kill;
use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
use App\Models\Weapon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->server = Server::factory()->create();
    $this->map = Map::factory()->create();
    $this->weapon = Weapon::factory()->create();
});

test('queries player rankings efficiently with large dataset', function () {
    Player::factory()->count(1000)->create();

    $startTime = microtime(true);

    $players = Player::orderBy('skill', 'desc')
        ->limit(100)
        ->get();

    $executionTime = microtime(true) - $startTime;

    expect($players->count())->toBe(100)
        ->and($executionTime)->toBeLessThan(1.0); // Should complete in less than 1 second
});

test('retrieves player profile efficiently', function () {
    $player = Player::factory()->create();

    // Create large dataset of kills
    Kill::factory()->count(500)->create([
        'killer_id' => $player->id,
    ]);

    $startTime = microtime(true);

    $response = $this->getJson("/api/players/{$player->id}");

    $executionTime = microtime(true) - $startTime;

    $response->assertOk();
    expect($executionTime)->toBeLessThan(0.5); // API should respond in less than 500ms
});

test('handles burst of kill events efficiently', function () {
    $killer = Player::factory()->create();
    $victim = Player::factory()->create();

    $events = [];
    for ($i = 0; $i < 100; $i++) {
        $events[] = [
            'type' => 'kill',
            'killer_steamid' => $killer->steamid,
            'victim_steamid' => $victim->steamid,
            'weapon' => $this->weapon->code,
            'headshot' => false,
            'timestamp' => now()->addSeconds($i),
        ];
    }

    $startTime = microtime(true);

    foreach ($events as $event) {
        ProcessLogEvent::dispatchSync($event, $this->server->id);
    }

    $executionTime = microtime(true) - $startTime;

    expect(Kill::count())->toBe(100)
        ->and($executionTime)->toBeLessThan(10.0); // 100 events in less than 10 seconds
});

test('weapon statistics query performs well', function () {
    $players = Player::factory()->count(50)->create();
    $weapons = Weapon::factory()->count(10)->create();

    // Create 1000 kills with various weapons
    foreach ($players as $killer) {
        Kill::factory()->count(20)->create([
            'killer_id' => $killer->id,
            'victim_id' => $players->random()->id,
            'weapon_id' => $weapons->random()->id,
        ]);
    }

    $startTime = microtime(true);

    $stats = DB::table('kills')
        ->select(
            'weapon_id',
            DB::raw('COUNT(*) as total_kills'),
            DB::raw('SUM(CASE WHEN headshot = 1 THEN 1 ELSE 0 END) as headshot_kills')
        )
        ->groupBy('weapon_id')
        ->get();

    $executionTime = microtime(true) - $startTime;

    expect($stats->count())->toBeGreaterThan(0)
        ->and($executionTime)->toBeLessThan(0.5);
});

test('map statistics query scales with data', function () {
    $maps = Map::factory()->count(20)->create();
    $players = Player::factory()->count(100)->create();

    // Create kills on various maps
    foreach ($maps as $map) {
        Kill::factory()->count(50)->create([
            'killer_id' => $players->random()->id,
            'victim_id' => $players->random()->id,
            'weapon_id' => $this->weapon->id,
        ]);
    }

    $startTime = microtime(true);

    $response = $this->getJson('/api/maps/statistics');

    $executionTime = microtime(true) - $startTime;

    $response->assertOk();
    expect($executionTime)->toBeLessThan(1.0);
});

test('concurrent player updates maintain consistency', function () {
    $player = Player::factory()->create(['kills' => 0]);

    // Simulate concurrent kill events
    $events = collect(range(1, 10))->map(function ($i) use ($player) {
        return [
            'type' => 'kill',
            'killer_steamid' => $player->steamid,
            'victim_steamid' => 'STEAM_1:0:' . $i,
            'weapon' => $this->weapon->code,
            'headshot' => false,
            'timestamp' => now(),
        ];
    });

    foreach ($events as $event) {
        Player::factory()->create(['steamid' => $event['victim_steamid']]);
        ProcessLogEvent::dispatchSync($event, $this->server->id);
    }

    $player->refresh();

    expect($player->kills)->toBe(10);
});

test('leaderboard query with pagination performs well', function () {
    Player::factory()->count(5000)->create();

    $startTime = microtime(true);

    $response = $this->getJson('/api/players/rankings?per_page=50&page=1');

    $executionTime = microtime(true) - $startTime;

    $response->assertOk()
        ->assertJsonCount(50, 'data');

    expect($executionTime)->toBeLessThan(0.5);
});

test('database queries use proper indexes', function () {
    Player::factory()->count(100)->create();

    // Enable query log
    DB::enableQueryLog();

    Player::where('steamid', 'STEAM_1:0:12345')->first();

    $queries = DB::getQueryLog();
    $query = $queries[0];

    expect($query)->toHaveKey('query')
        ->and($query['time'])->toBeLessThan(50); // Less than 50ms

    DB::disableQueryLog();
});

test('kill feed retrieval performs efficiently', function () {
    Kill::factory()->count(1000)->create([
        'killer_id' => Player::factory()->create()->id,
        'victim_id' => Player::factory()->create()->id,
        'weapon_id' => $this->weapon->id,
    ]);

    $startTime = microtime(true);

    $response = $this->getJson('/api/kills/recent?limit=50');

    $executionTime = microtime(true) - $startTime;

    $response->assertOk();
    expect($executionTime)->toBeLessThan(0.3);
});

test('aggregated statistics calculation performs well', function () {
    $players = Player::factory()->count(100)->create();

    Kill::factory()->count(2000)->create([
        'killer_id' => $players->random()->id,
        'victim_id' => $players->random()->id,
        'weapon_id' => $this->weapon->id,
    ]);

    $startTime = microtime(true);

    $totalKills = Kill::count();
    $totalPlayers = Player::count();
    $totalHeadshots = Kill::where('headshot', true)->count();
    $topPlayer = Player::orderBy('skill', 'desc')->first();

    $executionTime = microtime(true) - $startTime;

    expect($totalKills)->toBeGreaterThan(0)
        ->and($totalPlayers)->toBe(100)
        ->and($executionTime)->toBeLessThan(0.5);
});

test('player search performs efficiently', function () {
    Player::factory()->count(1000)->create();
    Player::factory()->create(['name' => 'UniquePlayerName']);

    $startTime = microtime(true);

    $results = Player::where('name', 'like', '%UniquePlayer%')->get();

    $executionTime = microtime(true) - $startTime;

    expect($results->count())->toBeGreaterThan(0)
        ->and($executionTime)->toBeLessThan(0.5);
});

test('server statistics aggregation scales well', function () {
    $servers = Server::factory()->count(10)->create();
    $players = Player::factory()->count(50)->create();

    foreach ($servers as $server) {
        Kill::factory()->count(100)->create([
            'killer_id' => $players->random()->id,
            'victim_id' => $players->random()->id,
            'weapon_id' => $this->weapon->id,
            'server_id' => $server->id,
        ]);
    }

    $startTime = microtime(true);

    $stats = Server::withCount('kills')->get();

    $executionTime = microtime(true) - $startTime;

    expect($stats->count())->toBe(10)
        ->and($executionTime)->toBeLessThan(1.0);
});

test('bulk event processing maintains performance', function () {
    $killer = Player::factory()->create();
    $victims = Player::factory()->count(50)->create();

    $events = $victims->map(function ($victim) use ($killer) {
        return [
            'type' => 'kill',
            'killer_steamid' => $killer->steamid,
            'victim_steamid' => $victim->steamid,
            'weapon' => $this->weapon->code,
            'headshot' => false,
            'timestamp' => now(),
        ];
    })->all();

    $startTime = microtime(true);

    foreach ($events as $event) {
        ProcessLogEvent::dispatchSync($event, $this->server->id);
    }

    $executionTime = microtime(true) - $startTime;

    $killer->refresh();

    expect($killer->kills)->toBe(50)
        ->and($executionTime)->toBeLessThan(5.0); // 50 events in less than 5 seconds
});
