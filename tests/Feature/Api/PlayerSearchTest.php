<?php

use App\Models\Game;
use App\Models\Player;

uses()->group('api');

beforeEach(function () {
    \Illuminate\Support\Facades\Cache::flush();
});

test('can search players by name', function () {
    Game::factory()->create(['code' => 'csgo']);
    Player::factory()->create(['last_name' => 'ProGamer123', 'game_code' => 'csgo', 'hide_ranking' => false]);
    Player::factory()->create(['last_name' => 'NoobMaster', 'game_code' => 'csgo', 'hide_ranking' => false]);
    Player::factory()->create(['last_name' => 'ProPlayer456', 'game_code' => 'csgo', 'hide_ranking' => false]);

    $response = $this->getJson('/api/players/search?q=Pro');

    $response->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('search is case insensitive', function () {
    Game::factory()->create(['code' => 'csgo']);
    Player::factory()->create(['last_name' => 'ProGamer', 'game_code' => 'csgo', 'hide_ranking' => false]);

    $response = $this->getJson('/api/players/search?q=progamer');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

test('search requires minimum 3 characters', function () {
    $response = $this->getJson('/api/players/search?q=ab');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['q']);
});

test('search can be filtered by game', function () {
    Game::factory()->create(['code' => 'csgo']);
    Game::factory()->create(['code' => 'tf2']);
    Player::factory()->create(['last_name' => 'Player1', 'game_code' => 'csgo', 'hide_ranking' => false]);
    Player::factory()->create(['last_name' => 'Player2', 'game_code' => 'tf2', 'hide_ranking' => false]);

    $response = $this->getJson('/api/players/search?q=Player&game=csgo');

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name'))->toBe('Player1');
});

test('search requires q parameter', function () {
    $response = $this->getJson('/api/players/search');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['q']);
});

test('search excludes hidden players', function () {
    Game::factory()->create(['code' => 'csgo']);
    Player::factory()->create(['last_name' => 'PlayerVisible', 'game_code' => 'csgo', 'hide_ranking' => false]);
    Player::factory()->create(['last_name' => 'PlayerHidden', 'game_code' => 'csgo', 'hide_ranking' => true]);

    $response = $this->getJson('/api/players/search?q=Player');

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name'))->toBe('PlayerVisible');
});

test('search results are paginated', function () {
    Game::factory()->create(['code' => 'csgo']);
    Player::factory()->count(30)->create([
        'last_name' => fn () => 'Player'.fake()->unique()->numberBetween(1, 100),
        'game_code' => 'csgo',
        'hide_ranking' => false,
    ]);

    $response = $this->getJson('/api/players/search?q=Player&per_page=10');

    $response->assertOk();

    expect($response->json('meta.per_page'))->toBe(10)
        ->and($response->json('data'))->toHaveCount(10);
});
