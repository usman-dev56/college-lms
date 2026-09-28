<?php

use App\Http\Controllers\Teacher\DashboardController;
use App\Http\Controllers\Teacher\TimetableController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:teacher'])
    ->prefix('teacher')
    ->name('teacher.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/timetable', [TimetableController::class, 'index'])->name('timetable');
    });
