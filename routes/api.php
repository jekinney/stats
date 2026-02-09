<?php

use App\Http\Controllers\Api\PlayerController;
use Illuminate\Support\Facades\Route;

Route::get('players/rankings', [PlayerController::class, 'rankings']);
Route::get('players/search', [PlayerController::class, 'search']);
Route::get('players/{player}', [PlayerController::class, 'show'])->name('api.players.show');
