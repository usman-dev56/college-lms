<?php

use App\Http\Controllers\Admin\AcademicSessionController;
use App\Http\Controllers\Admin\ClassController;
use App\Http\Controllers\Admin\ClassSubjectController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PeriodController;
use App\Http\Controllers\Admin\StreamController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Admin\TimetableController;
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

        // Periods - the daily timetable grid, configured per session.
        Route::resource('periods', PeriodController::class)->except(['show']);

        // Streams
        Route::resource('streams', StreamController::class)->except(['show']);

        // Subjects
        Route::resource('subjects', SubjectController::class)->except(['show']);

        // Classes
        // "class" is a reserved word in PHP, so the model is ClassModel and
        // the route parameter is bound explicitly as {class}.
        //
        // The assignment routes are declared before the resource so that
        // /classes/{class}/assignments is not swallowed by the resource.
        Route::get('classes/{class}/assignments', [ClassSubjectController::class, 'edit'])
            ->name('classes.assignments.edit');

        Route::put('classes/{class}/assignments', [ClassSubjectController::class, 'update'])
            ->name('classes.assignments.update');

        Route::resource('classes', ClassController::class)
            ->except(['show'])
            ->parameters(['classes' => 'class']);

        // Timetables
        // These sit after the resource deliberately: the paths are distinct
        // (/classes/{class}/timetable) so order does not matter, but keeping
        // them together after the resource reads better than interleaving.
        Route::get('classes/{class}/timetable', [TimetableController::class, 'edit'])
            ->name('classes.timetable.edit');

        Route::put('classes/{class}/timetable', [TimetableController::class, 'update'])
            ->name('classes.timetable.update');

        Route::get('classes/{class}/timetable/view', [TimetableController::class, 'show'])
            ->name('classes.timetable.show');

        // Teachers
        // The controller type-hints User and guards on the role, so a
        // non-teacher id in the URL is a 404 rather than a data leak.
        Route::resource('teachers', TeacherController::class);
    });
