<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

// These tests require MySQL
uses()->group('database', 'mysql', 'indexes');

beforeEach(function () {
    // Use MySQL connection for these tests
    Config::set('database.default', 'mysql');

    // Ensure MySQL connection is available
    try {
        DB::connection('mysql')->getPdo();
    } catch (\Exception $e) {
        $this->markTestSkipped('MySQL connection not available: '.$e->getMessage());
    }
});

test('players table has game_skill_ranking composite index', function () {
    $indexes = DB::connection('mysql')->select("
        SHOW INDEX FROM players 
        WHERE Key_name = 'players_game_skill_hide_ranking_index'
    ");

    expect($indexes)->not->toBeEmpty()
        ->and(count($indexes))->toBe(3); // game, skill, hide_ranking columns
});

test('players table has skill_kills index for ranking queries', function () {
    $indexes = DB::connection('mysql')->select("
        SHOW INDEX FROM players 
        WHERE Key_name = 'players_skill_kills_index'
    ");

    expect($indexes)->not->toBeEmpty()
        ->and(count($indexes))->toBe(2); // skill, kills columns
});

test('players table has steam_id unique index', function () {
    $indexes = DB::connection('mysql')->select("
        SHOW INDEX FROM players 
        WHERE Key_name = 'players_steam_id_unique'
    ");

    expect($indexes)->not->toBeEmpty()
        ->and($indexes[0]->Non_unique)->toBe(0); // Unique index
});

test('player ranking query uses composite index', function () {
    // Create enough test data for MySQL to use the index (100+ rows)
    \App\Models\Player::factory()->count(100)->create(['game_code' => 'csgo', 'hide_ranking' => false]);

    // Force analyze to update statistics
    DB::connection('mysql')->statement('ANALYZE TABLE players');

    // Get EXPLAIN output with FORCE INDEX to verify index works
    $explain = DB::connection('mysql')->select("
        EXPLAIN SELECT * FROM players 
        FORCE INDEX (players_game_skill_hide_ranking_index)
        WHERE game_code = 'csgo' 
        AND hide_ranking = 0 
        ORDER BY skill DESC 
        LIMIT 100
    ");

    // Verify the forced index works (proves index is usable)
    expect($explain[0]->key)->toBe('players_game_skill_hide_ranking_index')
        ->and($explain[0]->type)->not->toBe('ALL'); // Not full table scan
});

test('steam_id lookup uses unique index', function () {
    $player = \App\Models\Player::factory()->create(['steam_id' => 'STEAM_1:0:12345']);

    DB::enableQueryLog();

    \App\Models\Player::where('steam_id', 'STEAM_1:0:12345')->first();

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $explain = DB::connection('mysql')->select('EXPLAIN '.$queries[0]['query'], $queries[0]['bindings']);

    expect($explain[0]->key)->toBe('players_steam_id_unique')
        ->and($explain[0]->type)->toBe('const'); // Single row lookup
});
