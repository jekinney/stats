<?php

use App\Models\EventFrag;
use App\Models\Game;
use App\Models\Player;
use App\Models\Server;
use App\Models\Weapon;

test('can get map statistics by game', function () {
    $game = Game::factory()->create(['code' => 'csgo']);
    $server = Server::factory()->create(['game_code' => 'csgo']);
    $weapon = Weapon::factory()->create(['game_code' => 'csgo']);
    $killer = Player::factory()->create();
    $victim = Player::factory()->create();

    // Create frags for different maps
    EventFrag::factory()->count(10)->create([
        'server_id' => $server->id,
        'map' => 'de_dust2',
        'weapon_code' => $weapon->code,
        'killer_id' => $killer->id,
        'victim_id' => $victim->id,
    ]);
    EventFrag::factory()->count(5)->create([
        'server_id' => $server->id,
        'map' => 'de_inferno',
        'weapon_code' => $weapon->code,
        'killer_id' => $killer->id,
        'victim_id' => $victim->id,
    ]);

    // Create frags for tf2 (different game) - ServerFactory will create the game
    $tf2Server = Server::factory()->create(['game_code' => 'tf2']);
    EventFrag::factory()->count(3)->create([
        'server_id' => $tf2Server->id,
        'map' => 'cp_dustbowl',
        'weapon_code' => $weapon->code,
        'killer_id' => $killer->id,
        'victim_id' => $victim->id,
    ]);

    $response = $this->getJson('/api/maps/statistics?game=csgo');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'map',
                    'kills',
                ],
            ],
        ])
        ->assertJsonCount(2, 'data');
});

test('map statistics are ordered by kills descending', function () {
    $game = Game::factory()->create(['code' => 'csgo']);
    $server = Server::factory()->create(['game_code' => 'csgo']);
    $weapon = Weapon::factory()->create(['game_code' => 'csgo']);
    $killer = Player::factory()->create();
    $victim = Player::factory()->create();

    EventFrag::factory()->count(10)->create([
        'server_id' => $server->id,
        'map' => 'de_dust2',
        'weapon_code' => $weapon->code,
        'killer_id' => $killer->id,
        'victim_id' => $victim->id,
    ]);
    EventFrag::factory()->count(5)->create([
        'server_id' => $server->id,
        'map' => 'de_inferno',
        'weapon_code' => $weapon->code,
        'killer_id' => $killer->id,
        'victim_id' => $victim->id,
    ]);

    $response = $this->getJson('/api/maps/statistics?game=csgo');

    $maps = $response->json('data');
    expect($maps[0]['map'])->toBe('de_dust2')
        ->and($maps[0]['kills'])->toBe(10)
        ->and($maps[1]['map'])->toBe('de_inferno')
        ->and($maps[1]['kills'])->toBe(5);
});

test('map statistics only include specified game', function () {
    $game1 = Game::factory()->create(['code' => 'csgo']);
    $server1 = Server::factory()->create(['game_code' => 'csgo']);
    // ServerFactory will create tf2 game via firstOrCreate
    $server2 = Server::factory()->create(['game_code' => 'tf2']);
    $weapon = Weapon::factory()->create(['game_code' => 'csgo']);
    $killer = Player::factory()->create();
    $victim = Player::factory()->create();

    EventFrag::factory()->count(5)->create([
        'server_id' => $server1->id,
        'map' => 'de_dust2',
        'weapon_code' => $weapon->code,
        'killer_id' => $killer->id,
        'victim_id' => $victim->id,
    ]);
    EventFrag::factory()->count(3)->create([
        'server_id' => $server2->id,
        'map' => 'cp_dustbowl',
        'weapon_code' => $weapon->code,
        'killer_id' => $killer->id,
        'victim_id' => $victim->id,
    ]);

    $response = $this->getJson('/api/maps/statistics?game=csgo');

    $maps = $response->json('data');
    expect($maps)->toHaveCount(1)
        ->and($maps[0]['map'])->toBe('de_dust2');
});

test('map statistics requires game parameter', function () {
    $response = $this->getJson('/api/maps/statistics');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['game']);
});

test('map statistics validates game exists', function () {
    $response = $this->getJson('/api/maps/statistics?game=invalid');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['game']);
});

test('map statistics supports pagination', function () {
    $game = Game::factory()->create(['code' => 'csgo']);
    $server = Server::factory()->create(['game_code' => 'csgo']);
    $weapon = Weapon::factory()->create(['game_code' => 'csgo']);
    $killer = Player::factory()->create();
    $victim = Player::factory()->create();

    // Create 15 different maps
    for ($i = 1; $i <= 15; $i++) {
        EventFrag::factory()->count(5)->create([
            'server_id' => $server->id,
            'map' => "de_map_{$i}",
            'weapon_code' => $weapon->code,
            'killer_id' => $killer->id,
            'victim_id' => $victim->id,
        ]);
    }

    $response = $this->getJson('/api/maps/statistics?game=csgo&per_page=10');

    $response->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonStructure([
            'data',
            'links',
            'meta',
        ]);
});
