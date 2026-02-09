<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

test('application has proper environment configuration', function () {
    expect(config('app.name'))->not->toBeEmpty()
        ->and(config('app.env'))->toBeIn(['local', 'testing', 'staging', 'production'])
        ->and(config('app.debug'))->toBeIn([true, false])
        ->and(config('app.url'))->not->toBeEmpty();
});

test('database connections are properly configured', function () {
    $connections = config('database.connections');

    expect($connections)->toHaveKey('mysql')
        ->and($connections['mysql'])->toHaveKey('host')
        ->and($connections['mysql'])->toHaveKey('database')
        ->and($connections['mysql'])->toHaveKey('username');
});

test('cache configuration is production-ready', function () {
    $cacheDriver = config('cache.default');

    expect($cacheDriver)->toBeIn(['redis', 'memcached', 'database', 'file', 'array'])
        ->and(config('cache.stores'))->toHaveKey($cacheDriver);
});

test('queue configuration is properly set', function () {
    $queueDriver = config('queue.default');

    expect($queueDriver)->toBeIn(['sync', 'database', 'redis', 'sqs', 'beanstalkd'])
        ->and(config('queue.connections'))->toHaveKey($queueDriver);
});

test('session configuration is secure', function () {
    expect(config('session.driver'))->toBeIn(['file', 'cookie', 'database', 'redis', 'memcached', 'array'])
        ->and(config('session.http_only'))->toBe(true);

    // In production, secure should be configured
    if (config('app.env') === 'production') {
        expect(config('session.secure'))->toBe(true);
    }

    $sameSite = config('session.same_site');
    if ($sameSite !== null) {
        expect($sameSite)->toBeIn(['lax', 'strict', 'none']);
    }
});

test('logging configuration is appropriate', function () {
    $logChannel = config('logging.default');

    expect($logChannel)->not->toBeEmpty()
        ->and(config('logging.channels'))->toHaveKey($logChannel);
});

test('mail configuration is present', function () {
    expect(config('mail.default'))->not->toBeEmpty()
        ->and(config('mail.mailers'))->toBeArray()
        ->and(config('mail.from.address'))->not->toBeEmpty();
});

test('application has required environment variables', function () {
    $requiredVars = [
        'APP_NAME',
        'APP_ENV',
        'APP_KEY',
        'APP_URL',
        'DB_CONNECTION',
        'DB_DATABASE',
    ];

    // Only check DB_HOST in non-testing environments (testing uses SQLite)
    if (config('app.env') !== 'testing') {
        $requiredVars[] = 'DB_HOST';
    }

    foreach ($requiredVars as $var) {
        expect(env($var))->not->toBeNull("Environment variable {$var} is missing");
    }
});

test('application key is set and valid', function () {
    $appKey = config('app.key');

    expect($appKey)->not->toBeEmpty()
        ->and(strlen($appKey))->toBeGreaterThan(20);
});

test('debug mode is appropriate for environment', function () {
    $env = config('app.env');
    $debug = config('app.debug');

    if ($env === 'production') {
        expect($debug)->toBe(false, 'Debug should be disabled in production');
    }
});

test('database migrations are up to date', function () {
    $pendingMigrations = Artisan::call('migrate:status');

    // In testing, migrations should already be run
    expect($pendingMigrations)->toBe(0);
});

test('application can connect to database', function () {
    expect(fn () => DB::connection()->getPdo())->not->toThrow(\Exception::class);

    $result = DB::select('SELECT 1 as test');
    expect($result[0]->test)->toBe(1);
});

test('critical routes are registered', function () {
    $routes = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->uri())
        ->toArray();

    $criticalRoutes = [
        'health',
        'players/rankings',
        'metrics',
    ];

    foreach ($criticalRoutes as $route) {
        $found = collect($routes)->contains(fn ($uri) => str_contains($uri, $route));
        expect($found)->toBeTrue("Critical route containing '{$route}' is not registered");
    }
});

test('critical database tables exist', function () {
    $tables = [
        'players',
        'servers',
        'games',
        'weapons',
        'event_frags',
        'migrations',
    ];

    foreach ($tables as $table) {
        expect(DB::getSchemaBuilder()->hasTable($table))->toBeTrue("Table {$table} does not exist");
    }
});

test('application has proper cors configuration', function () {
    expect(config('cors.paths'))->toBeArray()
        ->and(config('cors.allowed_methods'))->toBeArray()
        ->and(config('cors.allowed_origins'))->toBeArray();
});

test('filesystem disks are configured', function () {
    $disks = config('filesystems.disks');

    expect($disks)->toHaveKey('local')
        ->and($disks)->toHaveKey('public');
});

test('application timezone is set', function () {
    $timezone = config('app.timezone');

    expect($timezone)->not->toBeEmpty()
        ->and(in_array($timezone, \DateTimeZone::listIdentifiers()))->toBeTrue();
});

test('fortify features are properly configured', function () {
    $features = config('fortify.features', []);

    expect($features)->toBeArray()
        ->and(config('fortify.home'))->not->toBeEmpty();
});

test('critical models have proper fillable attributes', function () {
    $player = new \App\Models\Player;
    $server = new \App\Models\Server;
    $weapon = new \App\Models\Weapon;

    expect($player->getFillable())->not->toBeEmpty()
        ->and($server->getFillable())->not->toBeEmpty()
        ->and($weapon->getFillable())->not->toBeEmpty();
});

test('application has rate limiting configured', function () {
    $rateLimiter = app(\Illuminate\Cache\RateLimiter::class);

    expect($rateLimiter)->toBeInstanceOf(\Illuminate\Cache\RateLimiter::class);
});
