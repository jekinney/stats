<?php

declare(strict_types=1);

use App\Models\EventFrag;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

test('health check endpoint returns overall system health', function () {
    $response = $this->getJson('/api/health');

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'database',
                'cache',
                'queue',
            ],
        ]);

    expect($response->json('status'))->toBeIn(['healthy', 'degraded', 'unhealthy']);
});

test('health check detects database connectivity', function () {
    $response = $this->getJson('/api/health');

    $response->assertOk();
    expect($response->json('checks.database.status'))->toBe('healthy')
        ->and($response->json('checks.database'))->toHaveKey('response_time');
});

test('health check detects cache connectivity', function () {
    Cache::put('health_check_test', true, 10);

    $response = $this->getJson('/api/health');

    $response->assertOk();
    expect($response->json('checks.cache.status'))->toBe('healthy');

    Cache::forget('health_check_test');
});

test('health check provides queue status', function () {
    $response = $this->getJson('/api/health');

    $response->assertOk();
    expect($response->json('checks.queue'))->toHaveKey('status');
});

test('metrics endpoint provides system statistics', function () {
    // Create some test data
    Player::factory()->count(10)->create();
    EventFrag::factory()->count(100)->create();
    Server::factory()->count(5)->create();

    $response = $this->getJson('/api/metrics');

    $response->assertOk()
        ->assertJsonStructure([
            'timestamp',
            'database' => [
                'total_players',
                'total_frags',
                'total_servers',
            ],
            'performance' => [
                'avg_response_time',
                'requests_per_minute',
            ],
        ]);

    expect($response->json('database.total_players'))->toBeGreaterThanOrEqual(10)
        ->and($response->json('database.total_frags'))->toBeGreaterThanOrEqual(100)
        ->and($response->json('database.total_servers'))->toBeGreaterThanOrEqual(5);
});

test('queue monitoring endpoint shows job statistics', function () {
    $response = $this->getJson('/api/monitoring/queues');

    $response->assertOk()
        ->assertJsonStructure([
            'queues' => [
                '*' => [
                    'name',
                    'jobs_pending',
                    'jobs_failed',
                ],
            ],
        ]);
});

test('cache monitoring shows hit rate and memory usage', function () {
    // Populate cache
    Cache::put('test_key_1', 'value1', 60);
    Cache::put('test_key_2', 'value2', 60);
    Cache::get('test_key_1'); // Cache hit

    $response = $this->getJson('/api/monitoring/cache');

    $response->assertOk()
        ->assertJsonStructure([
            'driver',
            'stats' => [
                'keys_count',
            ],
        ]);
});

test('database monitoring shows connection pool status', function () {
    $response = $this->getJson('/api/monitoring/database');

    $response->assertOk()
        ->assertJsonStructure([
            'connections' => [
                '*' => [
                    'name',
                    'driver',
                    'status',
                ],
            ],
            'slow_queries',
        ]);
});

test('system status endpoint aggregates all monitoring data', function () {
    $response = $this->getJson('/api/monitoring/status');

    $response->assertOk()
        ->assertJsonStructure([
            'health',
            'metrics',
            'uptime',
            'version',
        ]);
});

test('error rate monitoring tracks failed requests', function () {
    $response = $this->getJson('/api/monitoring/errors');

    $response->assertOk()
        ->assertJsonStructure([
            'total_errors',
            'error_rate',
            'recent_errors' => [
                '*' => [
                    'message',
                    'count',
                    'last_occurrence',
                ],
            ],
        ]);
});

test('performance monitoring tracks response times', function () {
    // Make several requests to generate performance data
    $this->getJson('/api/players/rankings?game=csgo');
    $this->getJson('/api/weapons/statistics?game=csgo');

    $response = $this->getJson('/api/monitoring/performance');

    $response->assertOk()
        ->assertJsonStructure([
            'endpoints' => [
                '*' => [
                    'path',
                    'avg_response_time',
                    'request_count',
                ],
            ],
        ]);
});
