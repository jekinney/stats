<?php

use App\Models\Game;
use App\Models\Server;

uses()->group('models');

test('server belongs to game', function () {
    $game = Game::factory()->create(['code' => 'csgo']);
    $server = Server::factory()->create(['game_code' => 'csgo']);

    expect($server->game)->toBeInstanceOf(Game::class)
        ->and($server->game->code)->toBe('csgo');
});

test('server has many event frags', function () {
    $server = Server::factory()->create();

    expect($server->eventFrags())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('server has name', function () {
    $server = Server::factory()->create([
        'name' => 'Test Server #1',
    ]);

    expect($server->name)->toBe('Test Server #1');
});

test('server has address and port', function () {
    $server = Server::factory()->create([
        'address' => '192.168.1.100',
        'port' => 27015,
    ]);

    expect($server->address)->toBe('192.168.1.100')
        ->and($server->port)->toBe(27015);
});

test('server has public address', function () {
    $server = Server::factory()->create([
        'public_address' => '203.0.113.10',
    ]);

    expect($server->public_address)->toBe('203.0.113.10');
});

test('server can be enabled or disabled', function () {
    $activeServer = Server::factory()->create(['enabled' => true]);
    $inactiveServer = Server::factory()->create(['enabled' => false]);

    expect($activeServer->enabled)->toBeTrue()
        ->and($inactiveServer->enabled)->toBeFalse();
});

test('server tracks current map', function () {
    $server = Server::factory()->create([
        'map' => 'de_dust2',
    ]);

    expect($server->map)->toBe('de_dust2');
});

test('server tracks last activity timestamp', function () {
    $server = Server::factory()->create([
        'last_activity' => now(),
    ]);

    expect($server->last_activity)->toBeInstanceOf(\DateTimeInterface::class);
});

// Scopes
test('active servers scope filters enabled servers', function () {
    Server::factory()->count(3)->create(['enabled' => true]);
    Server::factory()->count(2)->create(['enabled' => false]);

    $activeServers = Server::active()->get();

    expect($activeServers)->toHaveCount(3);
});

test('by game scope filters servers by game', function () {
    Server::factory()->count(3)->create(['game_code' => 'csgo']);
    Server::factory()->count(2)->create(['game_code' => 'tf2']);

    $csgoServers = Server::byGame('csgo')->get();

    expect($csgoServers)->toHaveCount(3);
});

test('online scope filters recently active servers', function () {
    Server::factory()->create(['last_activity' => now()->subMinutes(3)]);
    Server::factory()->create(['last_activity' => now()->subHours(1)]);

    $onlineServers = Server::online()->get();

    expect($onlineServers)->toHaveCount(1);
});
