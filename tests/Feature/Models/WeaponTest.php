<?php

use App\Models\Game;
use App\Models\Weapon;

uses()->group('models');

test('weapon belongs to game', function () {
    $game = Game::factory()->create(['code' => 'csgo']);
    $weapon = Weapon::factory()->create(['game_code' => 'csgo']);

    expect($weapon->game)->toBeInstanceOf(Game::class)
        ->and($weapon->game->code)->toBe('csgo');
});

test('weapon has many event frags', function () {
    $weapon = Weapon::factory()->create();

    expect($weapon->eventFrags())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('weapon has code as primary key', function () {
    $weapon = Weapon::factory()->create(['code' => 'ak47']);

    expect($weapon->code)->toBe('ak47')
        ->and($weapon->getKeyName())->toBe('code');
});

test('weapon has name', function () {
    $weapon = Weapon::factory()->create([
        'name' => 'AK-47',
    ]);

    expect($weapon->name)->toBe('AK-47');
});

test('weapon has kill modifier', function () {
    $weapon = Weapon::factory()->create([
        'modifier' => 1.5,
    ]);

    expect($weapon->modifier)->toBe(1.5);
});

test('weapon kill modifier can be negative', function () {
    $weapon = Weapon::factory()->create([
        'modifier' => -2.0,
    ]);

    expect($weapon->modifier)->toBe(-2.0);
});

test('weapon can be enabled or disabled', function () {
    $activeWeapon = Weapon::factory()->create(['enabled' => true]);
    $disabledWeapon = Weapon::factory()->create(['enabled' => false]);

    expect($activeWeapon->enabled)->toBeTrue()
        ->and($disabledWeapon->enabled)->toBeFalse();
});

// Scopes
test('active weapons scope filters enabled weapons', function () {
    Weapon::factory()->count(3)->create(['enabled' => true]);
    Weapon::factory()->count(2)->create(['enabled' => false]);

    $activeWeapons = Weapon::active()->get();

    expect($activeWeapons)->toHaveCount(3);
});

test('by game scope filters weapons by game', function () {
    Weapon::factory()->count(3)->create(['game_code' => 'csgo']);
    Weapon::factory()->count(2)->create(['game_code' => 'tf2']);

    $csgoWeapons = Weapon::byGame('csgo')->get();

    expect($csgoWeapons)->toHaveCount(3);
});

test('high modifier scope filters weapons with positive modifiers', function () {
    Weapon::factory()->create(['modifier' => 2.0]);
    Weapon::factory()->create(['modifier' => 1.5]);
    Weapon::factory()->create(['modifier' => -1.0]);
    Weapon::factory()->create(['modifier' => 0.5]);

    $highModWeapons = Weapon::highModifier()->get();

    expect($highModWeapons)->toHaveCount(2);
});
