<?php

use App\Models\Game;
use App\Models\Player;
use Illuminate\Support\Facades\Cache;

uses()->group('api');

beforeEach(function () {
    Cache::flush();
});

test('can get player rankings', function () {
    Game::factory()->create(['code' => 'csgo']);
    Player::factory()->count(10)->create(['game_code' => 'csgo']);

    $response = $this->getJson('/api/players/rankings?game=csgo');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'skill',
                    'kills',
                    'deaths',
                    'kd_ratio',
                ],
            ],
            'meta' => [
                'total',
                'per_page',
                'current_page',
            ],
        ]);
});

test('rankings are ordered by skill descending', function () {
    Game::factory()->create(['code' => 'csgo']);
    Player::factory()->create(['game_code' => 'csgo', 'skill' => 1000.5, 'hide_ranking' => false]);
    Player::factory()->create(['game_code' => 'csgo', 'skill' => 2000.5, 'hide_ranking' => false]);
    Player::factory()->create(['game_code' => 'csgo', 'skill' => 1500.5, 'hide_ranking' => false]);

    $response = $this->getJson('/api/players/rankings?game=csgo');

    $players = $response->json('data');

    expect($players[0]['skill'])->toBe(2000.5)
        ->and($players[1]['skill'])->toBe(1500.5)
        ->and($players[2]['skill'])->toBe(1000.5);
});

test('rankings exclude hidden players', function () {
    Game::factory()->create(['code' => 'csgo']);
    Player::factory()->count(3)->create(['game_code' => 'csgo', 'hide_ranking' => false]);
    Player::factory()->count(2)->create(['game_code' => 'csgo', 'hide_ranking' => true]);

    $response = $this->getJson('/api/players/rankings?game=csgo');

    expect($response->json('meta.total'))->toBe(3);
});

test('rankings pagination works', function () {
    Game::factory()->create(['code' => 'csgo']);
    Player::factory()->count(50)->create(['game_code' => 'csgo']);

    $response = $this->getJson('/api/players/rankings?game=csgo&per_page=10&page=2');

    $response->assertOk();

    expect($response->json('meta.current_page'))->toBe(2)
        ->and($response->json('meta.per_page'))->toBe(10);
});

test('rankings require game parameter', function () {
    $response = $this->getJson('/api/players/rankings');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['game']);
});

test('rankings validate game exists', function () {
    $response = $this->getJson('/api/players/rankings?game=invalid');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['game']);
});

test('rankings are cached for 5 minutes', function () {
    Game::factory()->create(['code' => 'csgo']);
    Player::factory()->count(5)->create(['game_code' => 'csgo', 'hide_ranking' => false]);

    // First request - should hit database
    $this->getJson('/api/players/rankings?game=csgo');

    // Add more players
    Player::factory()->count(5)->create(['game_code' => 'csgo', 'hide_ranking' => false]);

    // Second request - should return cached data
    $response = $this->getJson('/api/players/rankings?game=csgo');

    expect($response->json('meta.total'))->toBe(5); // Not 10
});
