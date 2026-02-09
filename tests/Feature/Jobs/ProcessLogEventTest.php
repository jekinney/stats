<?php

use App\Events\KillFeedEvent;
use App\Jobs\ProcessLogEvent;
use App\Models\EventFrag;
use App\Models\Game;
use App\Models\Player;
use App\Models\Server;
use App\Models\Weapon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('process log event job creates event frag', function () {
    Event::fake();

    $game = Game::factory()->create(['code' => 'csgo']);
    $server = Server::factory()->create(['game_code' => 'csgo']);
    $weapon = Weapon::factory()->create(['game_code' => 'csgo', 'code' => 'ak47']);
    $killer = Player::factory()->create(['steam_id' => 'STEAM_1:0:12345']);
    $victim = Player::factory()->create(['steam_id' => 'STEAM_1:0:67890']);

    $eventData = [
        'type' => 'kill',
        'server_id' => $server->id,
        'killer' => ['steam_id' => 'STEAM_1:0:12345'],
        'victim' => ['steam_id' => 'STEAM_1:0:67890'],
        'weapon' => 'ak47',
        'headshot' => true,
        'timestamp' => now(),
    ];

    ProcessLogEvent::dispatchSync($eventData);

    $this->assertDatabaseHas('event_frags', [
        'killer_id' => $killer->id,
        'victim_id' => $victim->id,
        'weapon_code' => 'ak47',
        'headshot' => true,
    ]);
});

test('process log event updates player statistics', function () {
    $game = Game::factory()->create(['code' => 'csgo']);
    $server = Server::factory()->create(['game_code' => 'csgo']);
    $weapon = Weapon::factory()->create(['game_code' => 'csgo', 'code' => 'ak47']);
    $killer = Player::factory()->create([
        'steam_id' => 'STEAM_1:0:12345',
        'kills' => 10,
    ]);
    $victim = Player::factory()->create([
        'steam_id' => 'STEAM_1:0:67890',
        'deaths' => 5,
    ]);

    $eventData = [
        'type' => 'kill',
        'server_id' => $server->id,
        'killer' => ['steam_id' => 'STEAM_1:0:12345'],
        'victim' => ['steam_id' => 'STEAM_1:0:67890'],
        'weapon' => 'ak47',
        'headshot' => false,
        'timestamp' => now(),
    ];

    ProcessLogEvent::dispatchSync($eventData);

    expect($killer->fresh()->kills)->toBe(11)
        ->and($victim->fresh()->deaths)->toBe(6);
});

test('process log event creates player if not exists', function () {
    $game = Game::factory()->create(['code' => 'csgo']);
    $server = Server::factory()->create(['game_code' => 'csgo']);
    $weapon = Weapon::factory()->create(['game_code' => 'csgo', 'code' => 'knife']);

    $eventData = [
        'type' => 'kill',
        'server_id' => $server->id,
        'killer' => [
            'steam_id' => 'STEAM_1:0:99999',
            'name' => 'NewPlayer',
        ],
        'victim' => [
            'steam_id' => 'STEAM_1:0:88888',
            'name' => 'AnotherPlayer',
        ],
        'weapon' => 'knife',
        'headshot' => false,
        'timestamp' => now(),
    ];

    ProcessLogEvent::dispatchSync($eventData);

    $this->assertDatabaseHas('players', [
        'steam_id' => 'STEAM_1:0:99999',
        'last_name' => 'NewPlayer',
    ]);

    $this->assertDatabaseHas('players', [
        'steam_id' => 'STEAM_1:0:88888',
        'last_name' => 'AnotherPlayer',
    ]);
});

test('process log event broadcasts kill feed event', function () {
    Event::fake([KillFeedEvent::class]);

    $game = Game::factory()->create(['code' => 'csgo']);
    $server = Server::factory()->create(['game_code' => 'csgo']);
    $weapon = Weapon::factory()->create(['game_code' => 'csgo', 'code' => 'ak47']);
    $killer = Player::factory()->create(['steam_id' => 'STEAM_1:0:12345']);
    $victim = Player::factory()->create(['steam_id' => 'STEAM_1:0:67890']);

    $eventData = [
        'type' => 'kill',
        'server_id' => $server->id,
        'killer' => ['steam_id' => 'STEAM_1:0:12345'],
        'victim' => ['steam_id' => 'STEAM_1:0:67890'],
        'weapon' => 'ak47',
        'headshot' => true,
        'timestamp' => now(),
    ];

    ProcessLogEvent::dispatchSync($eventData);

    Event::assertDispatched(KillFeedEvent::class);
});

test('process log event stores position coordinates', function () {
    $game = Game::factory()->create(['code' => 'csgo']);
    $server = Server::factory()->create(['game_code' => 'csgo']);
    $weapon = Weapon::factory()->create(['game_code' => 'csgo', 'code' => 'ak47']);
    $killer = Player::factory()->create(['steam_id' => 'STEAM_1:0:12345']);
    $victim = Player::factory()->create(['steam_id' => 'STEAM_1:0:67890']);

    $eventData = [
        'type' => 'kill',
        'server_id' => $server->id,
        'killer' => [
            'steam_id' => 'STEAM_1:0:12345',
            'position' => [-1234, 2345, 64],
        ],
        'victim' => [
            'steam_id' => 'STEAM_1:0:67890',
            'position' => [5678, -9012, 128],
        ],
        'weapon' => 'ak47',
        'headshot' => false,
        'timestamp' => now(),
    ];

    ProcessLogEvent::dispatchSync($eventData);

    $this->assertDatabaseHas('event_frags', [
        'killer_id' => $killer->id,
        'victim_id' => $victim->id,
        'pos_x' => -1234,
        'pos_y' => 2345,
        'pos_z' => 64,
    ]);
});

test('process log event ignores non-kill events', function () {
    Event::fake();

    $eventData = [
        'type' => 'chat',
        'player' => ['steam_id' => 'STEAM_1:0:12345', 'name' => 'Player1'],
        'message' => 'hello world',
        'timestamp' => now(),
    ];

    ProcessLogEvent::dispatchSync($eventData);

    expect(EventFrag::count())->toBe(0);
});
