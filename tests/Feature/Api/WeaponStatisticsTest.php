<?php

use App\Models\EventFrag;
use App\Models\Game;
use App\Models\Weapon;

uses()->group('api');

test('can get weapon statistics by game', function () {
    $game = Game::factory()->create(['code' => 'csgo']);
    $ak47 = Weapon::factory()->create(['code' => 'ak47', 'game_code' => 'csgo', 'name' => 'AK-47']);
    $awp = Weapon::factory()->create(['code' => 'awp', 'game_code' => 'csgo', 'name' => 'AWP']);

    // Create kill events
    EventFrag::factory()->count(10)->create(['weapon_code' => 'ak47']);
    EventFrag::factory()->count(5)->create(['weapon_code' => 'awp']);

    $response = $this->getJson('/api/weapons/statistics?game=csgo');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'code',
                    'name',
                    'kills',
                    'headshots',
                    'headshot_percentage',
                ],
            ],
        ]);
});

test('weapon statistics are ordered by kill count descending', function () {
    $game = Game::factory()->create(['code' => 'csgo']);
    $ak47 = Weapon::factory()->create(['code' => 'ak47', 'game_code' => 'csgo']);
    $awp = Weapon::factory()->create(['code' => 'awp', 'game_code' => 'csgo']);
    $m4a1 = Weapon::factory()->create(['code' => 'm4a1', 'game_code' => 'csgo']);

    EventFrag::factory()->count(5)->create(['weapon_code' => 'ak47']);
    EventFrag::factory()->count(10)->create(['weapon_code' => 'awp']);
    EventFrag::factory()->count(3)->create(['weapon_code' => 'm4a1']);

    $response = $this->getJson('/api/weapons/statistics?game=csgo');

    $weapons = $response->json('data');

    expect($weapons[0]['code'])->toBe('awp')
        ->and($weapons[0]['kills'])->toBe(10)
        ->and($weapons[1]['code'])->toBe('ak47')
        ->and($weapons[1]['kills'])->toBe(5)
        ->and($weapons[2]['code'])->toBe('m4a1')
        ->and($weapons[2]['kills'])->toBe(3);
});

test('weapon statistics calculate headshot percentage', function () {
    $game = Game::factory()->create(['code' => 'csgo']);
    $ak47 = Weapon::factory()->create(['code' => 'ak47', 'game_code' => 'csgo']);

    EventFrag::factory()->count(7)->create(['weapon_code' => 'ak47', 'headshot' => true]);
    EventFrag::factory()->count(3)->create(['weapon_code' => 'ak47', 'headshot' => false]);

    $response = $this->getJson('/api/weapons/statistics?game=csgo');

    $weapon = $response->json('data.0');

    expect((float) $weapon['headshot_percentage'])->toBe(70.0);
});

test('weapon statistics require game parameter', function () {
    $response = $this->getJson('/api/weapons/statistics');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['game']);
});

test('weapon statistics validate game exists', function () {
    $response = $this->getJson('/api/weapons/statistics?game=invalid');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['game']);
});

test('weapon statistics only include active weapons', function () {
    $game = Game::factory()->create(['code' => 'csgo']);
    $active = Weapon::factory()->create(['code' => 'ak47', 'game_code' => 'csgo', 'enabled' => true]);
    $disabled = Weapon::factory()->create(['code' => 'old_weapon', 'game_code' => 'csgo', 'enabled' => false]);

    EventFrag::factory()->count(5)->create(['weapon_code' => 'ak47']);
    EventFrag::factory()->count(10)->create(['weapon_code' => 'old_weapon']);

    $response = $this->getJson('/api/weapons/statistics?game=csgo');

    $weapons = $response->json('data');

    expect($weapons)->toHaveCount(1)
        ->and($weapons[0]['code'])->toBe('ak47');
});

test('weapon statistics can be paginated', function () {
    $game = Game::factory()->create(['code' => 'csgo']);

    foreach (range(1, 30) as $i) {
        $weapon = Weapon::factory()->create(['code' => "weapon{$i}", 'game_code' => 'csgo']);
        EventFrag::factory()->count(5)->create(['weapon_code' => "weapon{$i}"]);
    }

    $response = $this->getJson('/api/weapons/statistics?game=csgo&per_page=10');

    $response->assertOk();

    expect($response->json('meta.per_page'))->toBe(10)
        ->and($response->json('data'))->toHaveCount(10);
});
