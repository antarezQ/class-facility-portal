<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PapanController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MenuController::class, 'index'])->name('menu');

Route::get('/papan', [PapanController::class, 'index'])->name('papan.index');
Route::get('/papan/data', [PapanController::class, 'json'])->name('papan.json');

Route::get('/dashboard/{tab?}', [DashboardController::class, 'index'])->name('dashboard');
