<?php

use App\Http\Controllers\Student\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:student'])
    ->prefix('student')
    ->name('student.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    });