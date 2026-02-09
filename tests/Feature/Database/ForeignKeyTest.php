<?php

use App\Models\EventFrag;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

uses()->group('database');

beforeEach(function () {
    // Use MySQL for these tests (information_schema queries)
    Config::set('database.default', 'mysql');
});

test('players table has foreign key to games', function () {
    $constraints = DB::connection('mysql')->select("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'players' 
        AND REFERENCED_TABLE_NAME = 'games'
    ");

    expect($constraints)->not->toBeEmpty();
});

test('cannot create player with invalid game code', function () {
    // Create player directly bypassing factory to avoid auto-creating game
    Player::create([
        'game_code' => 'invalid_game_code',
        'steam_id' => 'STEAM_1:0:12345',
        'last_name' => 'Test Player',
        'skill' => 1000,
    ]);
})->throws(\Illuminate\Database\QueryException::class);

test('deleting game cascades to players', function () {
    $game = Game::factory()->create(['code' => 'testgame']);
    $player = Player::factory()->create(['game_code' => 'testgame']);

    $game->delete();

    expect(Player::find($player->id))->toBeNull();
});

test('events_frags has foreign key to players for killer', function () {
    $constraints = DB::connection('mysql')->select("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'event_frags' 
        AND COLUMN_NAME = 'killer_id'
        AND REFERENCED_TABLE_NAME = 'players'
    ");

    expect($constraints)->not->toBeEmpty();
});

test('events_frags has foreign key to players for victim', function () {
    $constraints = DB::connection('mysql')->select("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'event_frags' 
        AND COLUMN_NAME = 'victim_id'
        AND REFERENCED_TABLE_NAME = 'players'
    ");

    expect($constraints)->not->toBeEmpty();
});

test('deleting player cascades to event frags', function () {
    $killer = Player::factory()->create();
    $victim = Player::factory()->create();
    $eventFrag = EventFrag::factory()->create([
        'killer_id' => $killer->id,
        'victim_id' => $victim->id,
    ]);

    $killer->delete();

    expect(EventFrag::find($eventFrag->id))->toBeNull();
});

test('cannot create event frag with invalid killer id', function () {
    $victim = Player::factory()->create();

    EventFrag::factory()->create([
        'killer_id' => 999999,
        'victim_id' => $victim->id,
    ]);
})->throws(\Illuminate\Database\QueryException::class);

test('cannot create event frag with invalid victim id', function () {
    $killer = Player::factory()->create();

    EventFrag::factory()->create([
        'killer_id' => $killer->id,
        'victim_id' => 999999,
    ]);
})->throws(\Illuminate\Database\QueryException::class);
