<?php

use App\Enums\UserRole;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\AdmissionController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// The root URL is a router, not a page. There is no signed-out landing page:
// a guest needs a login form, and a signed-in user already has a role-specific
// dashboard that '/dashboard' cannot pick for them.
//
// Deliberately outside the 'auth' group so guests can still reach it and be
// bounced to login - putting it behind 'auth' would loop.
Route::get('/', function () {
    if (! Auth::check()) {
        return redirect()->route('login');
    }

    return match (Auth::user()->role) {
        UserRole::Admin => redirect()->route('admin.dashboard'),
        UserRole::Teacher => redirect()->route('teacher.dashboard'),
        UserRole::Student => redirect()->route('student.dashboard'),
    };
})->name('home');

// Public admissions.
//
// Deliberately outside every middleware group: an applicant has no account
// and cannot make one before being admitted, so putting these behind 'auth'
// would lock out exactly the people they exist for.
//
// The batch they may apply to is not a route concern - the form request
// checks admissions_open - so a closed batch produces a validation error
// rather than a missing route.
Route::get('/admissions/apply', [AdmissionController::class, 'create'])
    ->name('admissions.apply');

Route::post('/admissions/apply', [AdmissionController::class, 'store'])
    ->name('admissions.store');

// The application number is the only handle on a row here, since there is
// no login to check it against.
Route::get('/admissions/success/{application_number}', [AdmissionController::class, 'success'])
    ->name('admissions.success');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
