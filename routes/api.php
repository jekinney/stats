<?php

use App\Http\Controllers\Api\FragController;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\MapController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\MonitoringController;
use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\ServerController;
use App\Http\Controllers\Api\WeaponController;
use Illuminate\Support\Facades\Route;

Route::get('players/rankings', [PlayerController::class, 'rankings']);
Route::get('players/search', [PlayerController::class, 'search']);
Route::get('players/{player}', [PlayerController::class, 'show'])->name('api.players.show');

Route::get('weapons/statistics', [WeaponController::class, 'statistics']);

Route::get('servers', [ServerController::class, 'index']);
Route::get('servers/{server}', [ServerController::class, 'show']);

Route::get('maps/statistics', [MapController::class, 'statistics']);

Route::get('frags', [FragController::class, 'index']);
Route::get('frags/recent', [FragController::class, 'recent']);

// Health & Monitoring
Route::get('health', [HealthCheckController::class, 'index']);
Route::get('metrics', [MetricsController::class, 'index']);
Route::get('monitoring/queues', [MonitoringController::class, 'queues']);
Route::get('monitoring/cache', [MonitoringController::class, 'cache']);
Route::get('monitoring/database', [MonitoringController::class, 'database']);
Route::get('monitoring/status', [MonitoringController::class, 'status']);
Route::get('monitoring/errors', [MonitoringController::class, 'errors']);
Route::get('monitoring/performance', [MonitoringController::class, 'performance']);
