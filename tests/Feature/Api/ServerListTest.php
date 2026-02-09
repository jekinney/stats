<?php

use App\Models\Game;
use App\Models\Server;
use Carbon\Carbon;

uses()->group('api');

test('can get server list by game', function () {
    $game = Game::factory()->create(['code' => 'csgo']);
    Server::factory()->count(3)->create(['game_code' => 'csgo', 'enabled' => true]);
    Server::factory()->count(2)->create(['game_code' => 'tf2', 'enabled' => true]);

    $response = $this->getJson('/api/servers?game=csgo');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'address',
                    'port',
                    'public_address',
                    'map',
                    'online',
                    'last_activity',
                ],
            ],
        ]);

    expect($response->json('data'))->toHaveCount(3);
});

test('server list includes online status', function () {
    $game = Game::factory()->create(['code' => 'csgo']);
    $online = Server::factory()->create([
        'game_code' => 'csgo',
        'enabled' => true,
        'last_activity' => Carbon::now()->subMinutes(2),
    ]);
    $offline = Server::factory()->create([
        'game_code' => 'csgo',
        'enabled' => true,
        'last_activity' => Carbon::now()->subHours(1),
    ]);

    $response = $this->getJson('/api/servers?game=csgo');

    $servers = $response->json('data');

    $onlineServer = collect($servers)->firstWhere('id', $online->id);
    $offlineServer = collect($servers)->firstWhere('id', $offline->id);

    expect($onlineServer['online'])->toBeTrue()
        ->and($offlineServer['online'])->toBeFalse();
});

test('server list only shows enabled servers', function () {
    $game = Game::factory()->create(['code' => 'csgo']);
    Server::factory()->count(3)->create(['game_code' => 'csgo', 'enabled' => true]);
    Server::factory()->count(2)->create(['game_code' => 'csgo', 'enabled' => false]);

    $response = $this->getJson('/api/servers?game=csgo');

    expect($response->json('data'))->toHaveCount(3);
});

test('server list can filter by online status', function () {
    $game = Game::factory()->create(['code' => 'csgo']);
    Server::factory()->count(2)->create([
        'game_code' => 'csgo',
        'enabled' => true,
        'last_activity' => Carbon::now()->subMinutes(2),
    ]);
    Server::factory()->count(3)->create([
        'game_code' => 'csgo',
        'enabled' => true,
        'last_activity' => Carbon::now()->subHours(1),
    ]);

    $response = $this->getJson('/api/servers?game=csgo&online=1');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

test('server list requires game parameter', function () {
    $response = $this->getJson('/api/servers');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['game']);
});

test('server list validates game exists', function () {
    $response = $this->getJson('/api/servers?game=invalid');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['game']);
});

test('can get individual server details', function () {
    $game = Game::factory()->create(['code' => 'csgo']);
    $server = Server::factory()->create([
        'game_code' => 'csgo',
        'name' => 'Test Server',
        'address' => '192.168.1.100',
        'port' => 27015,
        'map' => 'de_dust2',
    ]);

    $response = $this->getJson("/api/servers/{$server->id}");

    $response->assertOk()
        ->assertJson([
            'data' => [
                'id' => $server->id,
                'name' => 'Test Server',
                'address' => '192.168.1.100',
                'port' => 27015,
                'map' => 'de_dust2',
            ],
        ]);
});

test('server details returns 404 for non-existent server', function () {
    $response = $this->getJson('/api/servers/99999');

    $response->assertNotFound();
});
