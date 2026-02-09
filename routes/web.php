<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PlayersController;
use App\Http\Controllers\ServersController;
use App\Http\Controllers\WeaponsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('players', [PlayersController::class, 'index'])->name('players.index');
    Route::get('players/{player}', [PlayersController::class, 'show'])->name('players.show');
    Route::get('weapons', [WeaponsController::class, 'index'])->name('weapons.index');
    Route::get('servers', [ServersController::class, 'index'])->name('servers.index');
});

require __DIR__.'/settings.php';
