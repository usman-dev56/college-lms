<?php

use App\Http\Controllers\Admin\AcademicSessionController;
use App\Http\Controllers\Admin\ClassController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\StreamController;
use App\Http\Controllers\Admin\SubjectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Academic Sessions
        Route::resource('academic-sessions', AcademicSessionController::class)
            ->except(['show']);

        Route::patch(
            'academic-sessions/{academic_session}/activate',
            [AcademicSessionController::class, 'activate']
        )->name('academic-sessions.activate');

        // Streams
        Route::resource('streams', StreamController::class)->except(['show']);

        // Subjects
        Route::resource('subjects', SubjectController::class)->except(['show']);

        // Classes
        // "class" is a reserved word in PHP, so the model is ClassModel and
        // the route parameter is bound explicitly as {class}.
        Route::resource('classes', ClassController::class)
            ->except(['show'])
            ->parameters(['classes' => 'class']);
    });