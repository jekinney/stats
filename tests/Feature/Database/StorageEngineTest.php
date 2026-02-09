<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

// These tests require MySQL
uses()->group('database', 'mysql');

beforeEach(function () {
    // Use MySQL connection for these tests
    Config::set('database.default', 'mysql');

    // Ensure MySQL connection is available
    try {
        DB::connection('mysql')->getPdo();
    } catch (\Exception $e) {
        $this->markTestSkipped('MySQL connection not available: '.$e->getMessage());
    }
});

test('all tables use InnoDB storage engine', function () {
    $myIsamTables = DB::connection('mysql')->select("
        SELECT TABLE_NAME 
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND ENGINE = 'MyISAM'
    ");

    expect($myIsamTables)->toBeEmpty();
});

test('tables have proper character set', function () {
    $tables = DB::connection('mysql')->select("
        SELECT TABLE_NAME, TABLE_COLLATION 
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_COLLATION != 'utf8mb4_unicode_ci'
        AND TABLE_TYPE = 'BASE TABLE'
    ");

    expect($tables)->toBeEmpty();
});

test('database uses utf8mb4 character set', function () {
    $database = DB::connection('mysql')->select('
        SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
        FROM information_schema.SCHEMATA
        WHERE SCHEMA_NAME = DATABASE()
    ')[0];

    expect($database->DEFAULT_CHARACTER_SET_NAME)->toBe('utf8mb4')
        ->and($database->DEFAULT_COLLATION_NAME)->toBe('utf8mb4_unicode_ci');
});
