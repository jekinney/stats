<?php

namespace App\Services;

use Carbon\Carbon;

class LogParser
{
    private const PATTERNS = [
        'kill' => '/^L (\d{2}\/\d{2}\/\d{4} - \d{2}:\d{2}:\d{2}): "(.+?)<(\d+)><(.+?)><(.+?)>" (?:\[(-?\d+) (-?\d+) (-?\d+)\] )?killed "(.+?)<(\d+)><(.+?)><(.+?)>" (?:\[(-?\d+) (-?\d+) (-?\d+)\] )?with "(.+?)"(?: \((.+?)\))?/',
        'connect' => '/^L (\d{2}\/\d{2}\/\d{4} - \d{2}:\d{2}:\d{2}): "(.+?)<(\d+)><(.+?)><>" connected, address "(.+?):(\d+)"/',
        'disconnect' => '/^L (\d{2}\/\d{2}\/\d{4} - \d{2}:\d{2}:\d{2}): "(.+?)<(\d+)><(.+?)><(.+?)>" disconnected \(reason "(.+?)"\)/',
        'chat' => '/^L (\d{2}\/\d{2}\/\d{4} - \d{2}:\d{2}:\d{2}): "(.+?)<(\d+)><(.+?)><(.+?)>" say "(.+?)"/',
        'team_chat' => '/^L (\d{2}\/\d{2}\/\d{4} - \d{2}:\d{2}:\d{2}): "(.+?)<(\d+)><(.+?)><(.+?)>" say_team "(.+?)"/',
        'map_change' => '/^L (\d{2}\/\d{2}\/\d{4} - \d{2}:\d{2}:\d{2}): Loading map "(.+?)"/',
        'round_end' => '/^L (\d{2}\/\d{2}\/\d{4} - \d{2}:\d{2}:\d{2}): World triggered "Round_End"/',
    ];

    public function parse(string $logLine): ?array
    {
        foreach (self::PATTERNS as $type => $pattern) {
            if (preg_match($pattern, $logLine, $matches)) {
                $methodName = 'parse'.str_replace('_', '', ucwords($type, '_'));

                return $this->$methodName($matches);
            }
        }

        return null;
    }

    private function parseKill(array $matches): array
    {
        return [
            'type' => 'kill',
            'timestamp' => $this->parseTimestamp($matches[1]),
            'killer' => [
                'name' => $matches[2],
                'id' => $matches[3],
                'steam_id' => $matches[4],
                'team' => $matches[5],
                'position' => isset($matches[6]) && $matches[6] !== '' ? [(int) $matches[6], (int) $matches[7], (int) $matches[8]] : null,
            ],
            'victim' => [
                'name' => $matches[9],
                'id' => $matches[10],
                'steam_id' => $matches[11],
                'team' => $matches[12],
                'position' => isset($matches[13]) && $matches[13] !== '' ? [(int) $matches[13], (int) $matches[14], (int) $matches[15]] : null,
            ],
            'weapon' => $matches[16],
            'headshot' => isset($matches[17]) && str_contains($matches[17], 'headshot'),
        ];
    }

    private function parseConnect(array $matches): array
    {
        return [
            'type' => 'connect',
            'timestamp' => $this->parseTimestamp($matches[1]),
            'player' => [
                'name' => $matches[2],
                'id' => $matches[3],
                'steam_id' => $matches[4],
            ],
            'ip_address' => $matches[5],
            'port' => (int) $matches[6],
        ];
    }

    private function parseDisconnect(array $matches): array
    {
        return [
            'type' => 'disconnect',
            'timestamp' => $this->parseTimestamp($matches[1]),
            'player' => [
                'name' => $matches[2],
                'id' => $matches[3],
                'steam_id' => $matches[4],
                'team' => $matches[5],
            ],
            'reason' => $matches[6],
        ];
    }

    private function parseChat(array $matches): array
    {
        return [
            'type' => 'chat',
            'timestamp' => $this->parseTimestamp($matches[1]),
            'player' => [
                'name' => $matches[2],
                'id' => $matches[3],
                'steam_id' => $matches[4],
                'team' => $matches[5],
            ],
            'message' => $matches[6],
        ];
    }

    private function parseTeamChat(array $matches): array
    {
        return [
            'type' => 'team_chat',
            'timestamp' => $this->parseTimestamp($matches[1]),
            'player' => [
                'name' => $matches[2],
                'id' => $matches[3],
                'steam_id' => $matches[4],
                'team' => $matches[5],
            ],
            'message' => $matches[6],
        ];
    }

    private function parseMapChange(array $matches): array
    {
        return [
            'type' => 'map_change',
            'timestamp' => $this->parseTimestamp($matches[1]),
            'map' => $matches[2],
        ];
    }

    private function parseRoundEnd(array $matches): array
    {
        return [
            'type' => 'round_end',
            'timestamp' => $this->parseTimestamp($matches[1]),
        ];
    }

    private function parseTimestamp(string $timestamp): Carbon
    {
        return Carbon::createFromFormat('m/d/Y - H:i:s', $timestamp);
    }
}
