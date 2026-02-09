<?php

use App\Events\KillFeedEvent;
use App\Models\EventFrag;
use App\Models\Game;
use App\Models\Player;
use App\Models\Server;
use App\Models\Weapon;
use Illuminate\Support\Facades\Event;

test('kill event is broadcast when frag is created', function () {
    Event::fake([KillFeedEvent::class]);

    $frag = EventFrag::factory()->create();

    event(new KillFeedEvent($frag));

    Event::assertDispatched(KillFeedEvent::class);
});

test('kill event contains killer and victim data', function () {
    $frag = EventFrag::factory()->create();

    $event = new KillFeedEvent($frag);

    expect($event->frag->killer_id)->not->toBeNull()
        ->and($event->frag->victim_id)->not->toBeNull();
});

test('kill event broadcasts on game-specific channel', function () {
    $game = Game::factory()->create(['code' => 'csgo']);
    $server = Server::factory()->create(['game_code' => 'csgo']);
    $weapon = Weapon::factory()->create(['game_code' => 'csgo']);
    $killer = Player::factory()->create();
    $victim = Player::factory()->create();

    $frag = EventFrag::factory()->create([
        'server_id' => $server->id,
        'weapon_code' => $weapon->code,
        'killer_id' => $killer->id,
        'victim_id' => $victim->id,
    ]);

    $event = new KillFeedEvent($frag);

    expect($event->broadcastOn()[0]->name)->toBe('game.csgo');
});

test('kill event includes weapon information', function () {
    $game = Game::factory()->create(['code' => 'csgo']);
    $server = Server::factory()->create(['game_code' => 'csgo']);
    $weapon = Weapon::factory()->create(['game_code' => 'csgo', 'code' => 'ak47']);
    $killer = Player::factory()->create(['last_name' => 'Player1']);
    $victim = Player::factory()->create(['last_name' => 'Player2']);

    $frag = EventFrag::factory()->create([
        'server_id' => $server->id,
        'weapon_code' => $weapon->code,
        'killer_id' => $killer->id,
        'victim_id' => $victim->id,
    ]);

    $event = new KillFeedEvent($frag);
    $data = $event->broadcastWith();

    expect($data['weapon'])->toBe('ak47')
        ->and($data['killer']['name'])->toBe('Player1')
        ->and($data['victim']['name'])->toBe('Player2');
});

test('kill event includes headshot status', function () {
    $game = Game::factory()->create(['code' => 'csgo']);
    $server = Server::factory()->create(['game_code' => 'csgo']);
    $weapon = Weapon::factory()->create(['game_code' => 'csgo']);
    $killer = Player::factory()->create();
    $victim = Player::factory()->create();

    $frag = EventFrag::factory()->create([
        'server_id' => $server->id,
        'weapon_code' => $weapon->code,
        'killer_id' => $killer->id,
        'victim_id' => $victim->id,
        'headshot' => true,
    ]);

    $event = new KillFeedEvent($frag);
    $data = $event->broadcastWith();

    expect($data['headshot'])->toBeTrue();
});

test('kill event includes timestamp', function () {
    $game = Game::factory()->create(['code' => 'csgo']);
    $server = Server::factory()->create(['game_code' => 'csgo']);
    $weapon = Weapon::factory()->create(['game_code' => 'csgo']);
    $killer = Player::factory()->create();
    $victim = Player::factory()->create();

    $frag = EventFrag::factory()->create([
        'server_id' => $server->id,
        'weapon_code' => $weapon->code,
        'killer_id' => $killer->id,
        'victim_id' => $victim->id,
    ]);

    $event = new KillFeedEvent($frag);
    $data = $event->broadcastWith();

    expect($data)->toHaveKey('timestamp')
        ->and($data['timestamp'])->not->toBeNull();
});

test('kill event broadcasts with correct event name', function () {
    $frag = EventFrag::factory()->create();

    $event = new KillFeedEvent($frag);

    expect($event->broadcastAs())->toBe('kill.feed');
});
