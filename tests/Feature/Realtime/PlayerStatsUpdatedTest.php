<?php

use App\Events\PlayerStatsUpdatedEvent;
use App\Models\Player;
use Illuminate\Support\Facades\Event;

test('player stats update event is broadcast when player updated', function () {
    Event::fake([PlayerStatsUpdatedEvent::class]);

    $player = Player::factory()->create();

    event(new PlayerStatsUpdatedEvent($player));

    Event::assertDispatched(PlayerStatsUpdatedEvent::class);
});

test('player stats event contains player data', function () {
    $player = Player::factory()->create([
        'last_name' => 'TestPlayer',
        'skill' => 1500.5,
        'kills' => 100,
        'deaths' => 50,
    ]);

    $event = new PlayerStatsUpdatedEvent($player);
    $data = $event->broadcastWith();

    expect($data['player']['id'])->toBe($player->id)
        ->and($data['player']['name'])->toBe('TestPlayer')
        ->and($data['player']['skill'])->toBe(1500.5)
        ->and($data['player']['kills'])->toBe(100)
        ->and($data['player']['deaths'])->toBe(50);
});

test('player stats event includes kd ratio', function () {
    $player = Player::factory()->create([
        'kills' => 100,
        'deaths' => 50,
    ]);

    $event = new PlayerStatsUpdatedEvent($player);
    $data = $event->broadcastWith();

    expect($data['player']['kd_ratio'])->toBe(2.0);
});

test('player stats event broadcasts on player-specific channel', function () {
    $player = Player::factory()->create();

    $event = new PlayerStatsUpdatedEvent($player);

    expect($event->broadcastOn()[0]->name)->toBe("player.{$player->id}");
});

test('player stats event broadcasts with correct event name', function () {
    $player = Player::factory()->create();

    $event = new PlayerStatsUpdatedEvent($player);

    expect($event->broadcastAs())->toBe('stats.updated');
});

test('player stats event includes rank change', function () {
    $player = Player::factory()->create(['skill' => 1500]);

    $event = new PlayerStatsUpdatedEvent($player, previousRank: 10, currentRank: 8);
    $data = $event->broadcastWith();

    expect($data)->toHaveKey('rank_change')
        ->and($data['rank_change']['previous'])->toBe(10)
        ->and($data['rank_change']['current'])->toBe(8);
});

test('player stats event can omit rank change when not provided', function () {
    $player = Player::factory()->create();

    $event = new PlayerStatsUpdatedEvent($player);
    $data = $event->broadcastWith();

    expect($data)->not->toHaveKey('rank_change');
});
