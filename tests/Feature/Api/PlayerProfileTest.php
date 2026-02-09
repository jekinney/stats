<?php

use App\Models\EventFrag;
use App\Models\Player;
use App\Models\Weapon;

uses()->group('api');

test('can get player profile', function () {
    $player = Player::factory()->create();

    $response = $this->getJson("/api/players/{$player->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'skill',
                'kills',
                'deaths',
                'kd_ratio',
                'headshots',
                'headshot_percentage',
                'recent_kills',
                'weapon_stats',
            ],
        ]);
});

test('player profile includes recent kills', function () {
    $player = Player::factory()->create();
    EventFrag::factory()->count(5)->create(['killer_id' => $player->id]);

    $response = $this->getJson("/api/players/{$player->id}");

    expect($response->json('data.recent_kills'))->toHaveCount(5);
});

test('player profile includes weapon statistics', function () {
    $player = Player::factory()->create();
    $weapon = Weapon::factory()->create(['code' => 'ak47', 'name' => 'AK-47']);

    EventFrag::factory()->count(10)->create([
        'killer_id' => $player->id,
        'weapon_code' => 'ak47',
    ]);

    $response = $this->getJson("/api/players/{$player->id}");

    $weapons = $response->json('data.weapon_stats');

    expect($weapons)->toHaveCount(1)
        ->and($weapons[0]['weapon'])->toBe('ak47')
        ->and($weapons[0]['kills'])->toBe(10);
});

test('player profile returns 404 for non-existent player', function () {
    $response = $this->getJson('/api/players/99999');

    $response->assertNotFound();
});

test('player profile calculates headshot percentage', function () {
    $player = Player::factory()->create();

    EventFrag::factory()->count(7)->create([
        'killer_id' => $player->id,
        'headshot' => true,
    ]);

    EventFrag::factory()->count(3)->create([
        'killer_id' => $player->id,
        'headshot' => false,
    ]);

    $response = $this->getJson("/api/players/{$player->id}");

    expect((float) $response->json('data.headshot_percentage'))->toBe(70.0);
});

test('player profile limits recent kills to 10', function () {
    $player = Player::factory()->create();
    EventFrag::factory()->count(20)->create(['killer_id' => $player->id]);

    $response = $this->getJson("/api/players/{$player->id}");

    expect($response->json('data.recent_kills'))->toHaveCount(10);
});

test('player profile weapon stats are ordered by kill count', function () {
    $player = Player::factory()->create();
    $weapon1 = Weapon::factory()->create(['code' => 'ak47']);
    $weapon2 = Weapon::factory()->create(['code' => 'awp']);
    $weapon3 = Weapon::factory()->create(['code' => 'm4a1']);

    EventFrag::factory()->count(5)->create(['killer_id' => $player->id, 'weapon_code' => 'ak47']);
    EventFrag::factory()->count(10)->create(['killer_id' => $player->id, 'weapon_code' => 'awp']);
    EventFrag::factory()->count(3)->create(['killer_id' => $player->id, 'weapon_code' => 'm4a1']);

    $response = $this->getJson("/api/players/{$player->id}");

    $weapons = $response->json('data.weapon_stats');

    expect($weapons[0]['weapon'])->toBe('awp')
        ->and($weapons[0]['kills'])->toBe(10)
        ->and($weapons[1]['weapon'])->toBe('ak47')
        ->and($weapons[1]['kills'])->toBe(5)
        ->and($weapons[2]['weapon'])->toBe('m4a1')
        ->and($weapons[2]['kills'])->toBe(3);
});
