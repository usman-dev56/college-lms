<?php

use App\Http\Controllers\Admin\AcademicSessionController;
use App\Http\Controllers\Admin\AdmissionController;
use App\Http\Controllers\Admin\ClassController;
use App\Http\Controllers\Admin\ClassSubjectController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EnrollmentController;
use App\Http\Controllers\Admin\PeriodController;
use App\Http\Controllers\Admin\StreamController;
use App\Http\Controllers\Admin\StudentBatchController;
use App\Http\Controllers\Admin\StudentController;
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

        // Students
        // Unlike teachers, which are modelled directly on the users table,
        // a student spans two tables: the account in users and the profile in
        // student_profiles. The controller binds {student} to the profile and
        // guards on the account's role, so a teacher or admin id is a 404
        // here too.
        Route::resource('students', StudentController::class);

        // Admissions
        // Not a resource: applications arrive from the public form, so there
        // is nothing to create, edit or delete from the admin side. The office
        // reads the list, opens one, and moves it through the workflow - so
        // the three actions are their own PATCH routes rather than an update
        // on the whole record, which would let one of them rewrite the
        // applicant's name and address by accident.
        Route::get('admissions', [AdmissionController::class, 'index'])
            ->name('admissions.index');

        // The merit list is declared BEFORE admissions/{admission} on
        // purpose. Laravel matches in declaration order, so the literal
        // "merit-list" path has to come first or it would be swallowed by
        // the {admission} wildcard and the controller would be asked to load
        // an application whose id is the string "merit-list".
        Route::get('admissions/merit-list', [AdmissionController::class, 'meritList'])
            ->name('admissions.merit-list');

        Route::get('admissions/{admission}', [AdmissionController::class, 'show'])
            ->name('admissions.show');
        Route::patch('admissions/{admission}/review', [AdmissionController::class, 'review'])
            ->name('admissions.review');
        Route::patch('admissions/{admission}/accept', [AdmissionController::class, 'accept'])
            ->name('admissions.accept');
        Route::patch('admissions/{admission}/reject', [AdmissionController::class, 'reject'])
            ->name('admissions.reject');
        Route::patch('admissions/{admission}/convert', [AdmissionController::class, 'convert'])
            ->name('admissions.convert');

        // Student Batches
        // Cohorts, which are separate from academic sessions: a batch spans
        // several sessions and several batches run at once. No show page - a
        // batch is read from the list, and its students arrive in 3.2.
        Route::resource('student-batches', StudentBatchController::class)
            ->except(['show']);

        // Class roster and enrollment
        // The class routes sit at the end of the group on purpose. They are
        // distinct paths (/classes/{class}/enrollments), so they cannot
        // collide with the classes resource - but they are placed after the
        // assignments and timetable routes for the same reason those were
        // grouped together earlier: keeping one class's sub-pages in one
        // block reads better than interleaving them.
        Route::get('classes/{class}/enrollments', [EnrollmentController::class, 'index'])
            ->name('classes.enrollments.index');
        Route::post('classes/{class}/enrollments', [EnrollmentController::class, 'store'])
            ->name('classes.enrollments.store');

        // The bare DELETE on enrollments must come before the one with a
        // parameter, or Laravel would read "enrollments" as an {enrollment}
        // id and try to find an enrollment with that name.
        Route::delete('enrollments', [EnrollmentController::class, 'bulkUnenroll'])
            ->name('enrollments.bulk-unenroll');
        Route::delete('enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])
            ->name('enrollments.destroy');

        Route::get('students/{student}/enrollments', [EnrollmentController::class, 'studentHistory'])
            ->name('students.enrollments.index');
    });
