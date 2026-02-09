<?php

use App\Services\LogParser;

test('can parse player kill event', function () {
    $logLine = 'L 02/09/2026 - 12:34:56: "Player1<123><STEAM_1:0:12345><CT>" killed "Player2<456><STEAM_1:0:67890><TERRORIST>" with "ak47" (headshot)';

    $parser = new LogParser;
    $event = $parser->parse($logLine);

    expect($event['type'])->toBe('kill')
        ->and($event['killer']['name'])->toBe('Player1')
        ->and($event['killer']['steam_id'])->toBe('STEAM_1:0:12345')
        ->and($event['victim']['name'])->toBe('Player2')
        ->and($event['weapon'])->toBe('ak47')
        ->and($event['headshot'])->toBeTrue();
});

test('can parse player connect event', function () {
    $logLine = 'L 02/09/2026 - 12:34:56: "Player1<123><STEAM_1:0:12345><>" connected, address "192.168.1.1:27005"';

    $parser = new LogParser;
    $event = $parser->parse($logLine);

    expect($event['type'])->toBe('connect')
        ->and($event['player']['name'])->toBe('Player1')
        ->and($event['player']['steam_id'])->toBe('STEAM_1:0:12345')
        ->and($event['ip_address'])->toBe('192.168.1.1');
});

test('can parse player disconnect event', function () {
    $logLine = 'L 02/09/2026 - 12:34:56: "Player1<123><STEAM_1:0:12345><CT>" disconnected (reason "Disconnect by user.")';

    $parser = new LogParser;
    $event = $parser->parse($logLine);

    expect($event['type'])->toBe('disconnect')
        ->and($event['player']['name'])->toBe('Player1')
        ->and($event['reason'])->toContain('user');
});

test('can parse player chat event', function () {
    $logLine = 'L 02/09/2026 - 12:34:56: "Player1<123><STEAM_1:0:12345><CT>" say "gg wp"';

    $parser = new LogParser;
    $event = $parser->parse($logLine);

    expect($event['type'])->toBe('chat')
        ->and($event['message'])->toBe('gg wp');
});

test('can parse team chat event', function () {
    $logLine = 'L 02/09/2026 - 12:34:56: "Player1<123><STEAM_1:0:12345><CT>" say_team "rush B"';

    $parser = new LogParser;
    $event = $parser->parse($logLine);

    expect($event['type'])->toBe('team_chat')
        ->and($event['message'])->toBe('rush B');
});

test('can parse map change event', function () {
    $logLine = 'L 02/09/2026 - 12:34:56: Loading map "de_dust2"';

    $parser = new LogParser;
    $event = $parser->parse($logLine);

    expect($event['type'])->toBe('map_change')
        ->and($event['map'])->toBe('de_dust2');
});

test('can parse round end event', function () {
    $logLine = 'L 02/09/2026 - 12:34:56: World triggered "Round_End"';

    $parser = new LogParser;
    $event = $parser->parse($logLine);

    expect($event['type'])->toBe('round_end');
});

test('parser handles malformed log lines gracefully', function () {
    $logLine = 'Invalid log format';

    $parser = new LogParser;
    $event = $parser->parse($logLine);

    expect($event)->toBeNull();
});

test('parser extracts position coordinates from kill event', function () {
    $logLine = 'L 02/09/2026 - 12:34:56: "Player1<123><STEAM_1:0:12345><CT>" [-1234 2345 64] killed "Player2<456><STEAM_1:0:67890><TERRORIST>" [5678 -9012 128] with "ak47"';

    $parser = new LogParser;
    $event = $parser->parse($logLine);

    expect($event['killer']['position'])->toBe([-1234, 2345, 64])
        ->and($event['victim']['position'])->toBe([5678, -9012, 128]);
});

test('parser extracts timestamp from log line', function () {
    $logLine = 'L 02/09/2026 - 12:34:56: "Player1<123><STEAM_1:0:12345><CT>" say "hello"';

    $parser = new LogParser;
    $event = $parser->parse($logLine);

    expect($event)->toHaveKey('timestamp')
        ->and($event['timestamp'])->not->toBeNull();
});
