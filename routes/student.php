<?php

use App\Http\Controllers\Student\AttendanceController;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\ProfileController;
use App\Http\Controllers\Student\TimetableController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:student'])
    ->prefix('student')
    ->name('student.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // The student's own record, read-only. No route parameter, so there
        // is no id to tamper with.
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile');

        Route::get('/timetable', [TimetableController::class, 'index'])->name('timetable');

        // The student's own attendance record. No route parameter, so there
        // is no id to tamper with - the summary is resolved from the
        // signed-in account.
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance');
    });
