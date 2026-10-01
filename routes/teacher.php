<?php

use App\Http\Controllers\Teacher\AttendanceController;
use App\Http\Controllers\Teacher\DashboardController;
use App\Http\Controllers\Teacher\TimetableController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:teacher'])
    ->prefix('teacher')
    ->name('teacher.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/timetable', [TimetableController::class, 'index'])->name('timetable');

        // Attendance marking.
        //
        // The marking routes take their target as query parameters rather
        // than path segments, because the teacher picks one cell out of a
        // day's grid rather than navigating a hierarchy. Both verbs share one
        // URL: the GET reads the register, the POST writes it, and the GET
        // renders read-only the moment the POST has succeeded.
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('/attendance/mark', [AttendanceController::class, 'mark'])->name('attendance.mark');
        Route::post('/attendance/mark', [AttendanceController::class, 'store'])->name('attendance.store');
    });
