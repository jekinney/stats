<?php

use App\Models\Game;
use App\Models\Player;

uses()->group('models');

test('player belongs to game', function () {
    $player = Player::factory()->create(['game_code' => 'csgo']);

    expect($player->game)->toBeInstanceOf(Game::class)
        ->and($player->game->code)->toBe('csgo');
});

test('player has many kills', function () {
    $player = Player::factory()->create();

    expect($player->kills())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('player has many deaths', function () {
    $player = Player::factory()->create();

    expect($player->deaths())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('player calculates kd ratio', function () {
    $player = Player::factory()->create([
        'kills' => 100,
        'deaths' => 50,
    ]);

    expect($player->kd_ratio)->toBe(2.0);
});

test('player kd ratio handles zero deaths', function () {
    $player = Player::factory()->create([
        'kills' => 100,
        'deaths' => 0,
    ]);

    expect($player->kd_ratio)->toBe(100.0);
});

test('player has skill points', function () {
    $player = Player::factory()->create([
        'skill' => 1500.50,
    ]);

    expect($player->skill)->toBe(1500.50);
});

test('player can be hidden from rankings', function () {
    $player = Player::factory()->create([
        'hide_ranking' => true,
    ]);

    expect($player->hide_ranking)->toBeTrue();
});

// Scopes
test('active players scope excludes hidden', function () {
    Player::factory()->count(3)->create(['hide_ranking' => false]);
    Player::factory()->count(2)->create(['hide_ranking' => true]);

    $activePlayers = Player::active()->get();

    expect($activePlayers)->toHaveCount(3);
});

test('by game scope filters correctly', function () {
    Player::factory()->count(3)->create(['game_code' => 'csgo']);
    Player::factory()->count(2)->create(['game_code' => 'tf2']);

    $csgoPlayers = Player::byGame('csgo')->get();

    expect($csgoPlayers)->toHaveCount(3);
});

test('top ranked scope orders by skill', function () {
    Player::factory()->create(['skill' => 1000]);
    $topPlayer = Player::factory()->create(['skill' => 2000]);
    Player::factory()->create(['skill' => 1500]);

    $top = Player::topRanked()->first();

    expect($top->id)->toBe($topPlayer->id);
});
