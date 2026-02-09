<?php

use App\Events\ServerStatusChangedEvent;
use App\Models\Game;
use App\Models\Server;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

test('server status event is broadcast when server status changes', function () {
    Event::fake([ServerStatusChangedEvent::class]);

    $server = Server::factory()->create();

    event(new ServerStatusChangedEvent($server, 'online'));

    Event::assertDispatched(ServerStatusChangedEvent::class);
});

test('server status event contains server data', function () {
    $game = Game::factory()->create(['code' => 'csgo']);
    $server = Server::factory()->create([
        'game_code' => 'csgo',
        'name' => 'Test Server',
        'address' => '192.168.1.1',
        'port' => 27015,
        'map' => 'de_dust2',
    ]);

    $event = new ServerStatusChangedEvent($server, 'online');
    $data = $event->broadcastWith();

    expect($data['server']['id'])->toBe($server->id)
        ->and($data['server']['name'])->toBe('Test Server')
        ->and($data['server']['address'])->toBe('192.168.1.1')
        ->and($data['server']['port'])->toBe(27015)
        ->and($data['server']['map'])->toBe('de_dust2');
});

test('server status event broadcasts on game-specific channel', function () {
    $game = Game::factory()->create(['code' => 'csgo']);
    $server = Server::factory()->create(['game_code' => 'csgo']);

    $event = new ServerStatusChangedEvent($server, 'online');

    expect($event->broadcastOn()[0]->name)->toBe('game.csgo.servers');
});

test('server status event includes status', function () {
    $server = Server::factory()->create();

    $event = new ServerStatusChangedEvent($server, 'online');
    $data = $event->broadcastWith();

    expect($data['status'])->toBe('online');
});

test('server status event broadcasts with correct event name', function () {
    $server = Server::factory()->create();

    $event = new ServerStatusChangedEvent($server, 'online');

    expect($event->broadcastAs())->toBe('server.status');
});

test('server status event can indicate offline status', function () {
    $server = Server::factory()->create([
        'last_activity' => Carbon::now()->subHours(2),
    ]);

    $event = new ServerStatusChangedEvent($server, 'offline');
    $data = $event->broadcastWith();

    expect($data['status'])->toBe('offline');
});

test('server status event includes player count when provided', function () {
    $server = Server::factory()->create();

    $event = new ServerStatusChangedEvent($server, 'online', playerCount: 15, maxPlayers: 32);
    $data = $event->broadcastWith();

    expect($data)->toHaveKey('player_count')
        ->and($data['player_count'])->toBe(15)
        ->and($data['max_players'])->toBe(32);
});

test('server status event can omit player count when not provided', function () {
    $server = Server::factory()->create();

    $event = new ServerStatusChangedEvent($server, 'online');
    $data = $event->broadcastWith();

    expect($data)->not->toHaveKey('player_count');
});
