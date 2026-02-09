<?php

use App\Http\Controllers\Api\MapController;
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
