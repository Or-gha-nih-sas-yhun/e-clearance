<?php

use App\Http\Controllers\AccountRecoveryController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdminPersonnelController;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\LoginCaptchaController;
use App\Http\Controllers\ClearanceFormController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportCsvController;
use App\Http\Controllers\InstructorAssignmentController;
use App\Http\Controllers\InstructorController;
use App\Http\Controllers\NotificationsApiController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PortalPasswordCodeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegistrarController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentRegistryController;
use App\Http\Controllers\SubjectCodeController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\TreasurerController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest Routes
|--------------------------------------------------------------------------
*/

// Public portal directory and system landing page
Route::view('/', 'landing')->name('landing');
Route::get('/auth/captcha/{portal}', LoginCaptchaController::class)
    ->whereIn('portal', ['main-admin', 'student', 'instructor', 'office', 'treasurer', 'registrar'])
    ->middleware('throttle:30,1')
    ->name('auth.captcha');
Route::get('/clearance/verify/{token}', [ClearanceFormController::class, 'verifyToken'])
    ->whereAlphaNumeric('token')
    ->middleware('throttle:30,1')
    ->name('clearance.verify');

Route::get('/forgot-password/{portal}', [AccountRecoveryController::class, 'show'])
    ->whereIn('portal', ['main-admin', 'student', 'instructor', 'office', 'treasurer', 'registrar'])
    ->name('account-recovery.show');
Route::post('/forgot-password/{portal}', [AccountRecoveryController::class, 'sendResetLink'])
    ->whereIn('portal', ['main-admin', 'student', 'instructor', 'office', 'treasurer', 'registrar'])
    ->middleware('throttle:password-reset')
    ->name('account-recovery.send');
Route::get('/reset-account-password/{portal}/{token}', [AccountRecoveryController::class, 'showReset'])
    ->whereIn('portal', ['main-admin', 'student', 'instructor', 'office', 'treasurer', 'registrar'])
    ->middleware('throttle:30,1')->name('account-recovery.reset');
Route::post('/reset-account-password/{portal}', [AccountRecoveryController::class, 'reset'])
    ->whereIn('portal', ['main-admin', 'student', 'instructor', 'office', 'treasurer', 'registrar'])
    ->middleware('throttle:password-reset')
    ->name('account-recovery.update');

Route::prefix('portal-password-recovery/{portal}')
    ->whereIn('portal', ['main-admin', 'instructor', 'office', 'registrar', 'treasurer'])
    ->name('portal-password-recovery.')
    ->group(function () {
        Route::post('send-code', [PortalPasswordCodeController::class, 'sendCode'])
            ->middleware('throttle:otp-send')->name('send-code');
        Route::post('verify-code', [PortalPasswordCodeController::class, 'verifyCode'])
            ->middleware('throttle:otp-verify')->name('verify-code');
        Route::post('reset', [PortalPasswordCodeController::class, 'resetPassword'])
            ->middleware('throttle:password-reset')->name('reset');
        Route::post('cancel', [PortalPasswordCodeController::class, 'cancel'])->name('cancel');
    });

Route::middleware('guest:admin')->group(function () {

    // Main Admin Login Page
    Route::get('/mainAdmin', [AdminAuthController::class, 'showLogin'])
        ->name('login');

    // Optional: redirect /mainAdmin/login to /mainAdmin
    Route::redirect('/mainAdmin/login', '/mainAdmin');

    // Login Process
    Route::post('/login', [AdminAuthController::class, 'login'])
        ->middleware(['throttle:login', 'recaptcha:login'])
        ->name('login.post');
    Route::post('/mainAdmin/login-code', [AdminAuthController::class, 'verifyLoginCode'])
        ->middleware('throttle:otp-verify')->name('login.otp.verify');
    Route::post('/mainAdmin/login-code/resend', [AdminAuthController::class, 'resendLoginCode'])
        ->middleware('throttle:otp-send')->name('login.otp.resend');
    Route::post('/mainAdmin/login-code/cancel', [AdminAuthController::class, 'cancelLoginCode'])
        ->name('login.otp.cancel');
});

/*
|--------------------------------------------------------------------------
| Dashboard Redirect
|--------------------------------------------------------------------------
*/

Route::get('/dashboard', function () {
    if (session()->has('admin_id')) {
        return redirect()->route('dashboard');
    }

    foreach (['instructor', 'office', 'registrar', 'treasurer', 'student'] as $guard) {
        if (Auth::guard($guard)->check()) {
            return redirect()->route($guard.'.dashboard');
        }
    }

    return redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

Route::post('/logout', [AdminAuthController::class, 'logout'])
    ->middleware('admin.auth', 'no.history')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Protected Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['admin.auth', 'no.history'])
    ->prefix('mainAdmin')
    ->group(function () {

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');

        // Students
        Route::get('/students', [StudentController::class, 'index'])->name('students.index');
        Route::post('/students', [StudentController::class, 'store'])->name('students.store');
        Route::put('/students/{student_id}', [StudentController::class, 'update'])->name('students.update');
        Route::delete('/students/{student_id}', [StudentController::class, 'destroy'])->name('students.destroy');
        Route::patch('/students/{student_id}/reset', [StudentController::class, 'reset'])->name('students.reset');

        // Roster of Microsoft accounts allowed to self-register a student account.
        Route::get('/student-registry', [StudentRegistryController::class, 'index'])->name('student-registry.index');
        Route::post('/student-registry', [StudentRegistryController::class, 'store'])->name('student-registry.store');
        Route::put('/student-registry/{id}', [StudentRegistryController::class, 'update'])->whereNumber('id')->name('student-registry.update');
        Route::delete('/student-registry/{id}', [StudentRegistryController::class, 'destroy'])->whereNumber('id')->name('student-registry.destroy');

        // End-of-term maintenance. Destructive; each action needs a typed phrase.
        Route::get('/settings', [SystemSettingsController::class, 'index'])->name('settings.index');
        Route::get('/settings/clearance-archive', [SystemSettingsController::class, 'archive'])->name('settings.clearance-archive');
        Route::post('/settings/term', [SystemSettingsController::class, 'term'])->name('settings.term');
        Route::post('/settings/promote-students', [SystemSettingsController::class, 'promote'])->name('settings.promote');
        Route::post('/settings/reset-clearance', [SystemSettingsController::class, 'reset'])->name('settings.reset');

        // Instructors
        Route::get('/instructors', [InstructorController::class, 'index'])->name('instructors.index');
        Route::post('/instructors', [InstructorController::class, 'store'])->name('instructors.store');
        Route::put('/instructors/{instructor_id}', [InstructorController::class, 'update'])->name('instructors.update');
        Route::delete('/instructors/{instructor_id}', [InstructorController::class, 'destroy'])->name('instructors.destroy');
        Route::patch('/instructors/{instructor_id}/reset', [InstructorController::class, 'reset'])->name('instructors.reset');

        // Personnel
        Route::get('/personnel', [AdminPersonnelController::class, 'index'])->name('personnel.index');
        Route::post('/personnel', [AdminPersonnelController::class, 'store'])->name('personnel.store');
        Route::put('/personnel/{id}', [AdminPersonnelController::class, 'update'])->name('personnel.update');
        Route::delete('/personnel/{id}', [AdminPersonnelController::class, 'destroy'])->name('personnel.destroy');

        // Registrar
        Route::get('/registrar-list', [RegistrarController::class, 'index'])->name('registrar.index');
        Route::post('/registrar-list', [RegistrarController::class, 'store'])->name('registrar.store');
        Route::put('/registrar-list/{id}', [RegistrarController::class, 'update'])->name('registrar.update');
        Route::delete('/registrar-list/{id}', [RegistrarController::class, 'destroy'])->name('registrar.destroy');

        // Subject Codes
        Route::get('/subject-codes', [SubjectCodeController::class, 'index'])->name('subjects.index');
        Route::post('/subject-codes', [SubjectCodeController::class, 'store'])->name('subjects.store');
        Route::put('/subject-codes/{id}', [SubjectCodeController::class, 'update'])->name('subjects.update');
        Route::delete('/subject-codes/{id}', [SubjectCodeController::class, 'destroy'])->name('subjects.destroy');

        // Sections
        Route::get('/sections', [SectionController::class, 'index'])->name('sections.index');
        Route::post('/sections', [SectionController::class, 'store'])->name('sections.store');
        Route::put('/sections/{id}', [SectionController::class, 'update'])->name('sections.update');
        Route::delete('/sections/{id}', [SectionController::class, 'destroy'])->name('sections.destroy');

        // AJAX
        Route::get('/api/sections', [SectionController::class, 'getSections'])->name('api.sections');
        Route::get('/api/subjects', [SubjectCodeController::class, 'getSubjects'])->name('api.subjects');

        // Instructor Assignments
        Route::get('/assignments', [InstructorAssignmentController::class, 'index'])->name('assignments.index');
        Route::post('/assignments', [InstructorAssignmentController::class, 'store'])->name('assignments.store');
        Route::put('/assignments/{id}', [InstructorAssignmentController::class, 'update'])->name('assignments.update');
        Route::delete('/assignments/{id}', [InstructorAssignmentController::class, 'destroy'])->name('assignments.destroy');

        // User activity trail (devices, sign-ins, and recorded changes)
        Route::get('/activity', [ActivityLogController::class, 'index'])->name('activity.index');

        // Profile
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

        // Password
        Route::put('/password', [PasswordController::class, 'update'])->name('password.update');

        // Treasurer
        Route::get('/treasurers', [TreasurerController::class, 'index'])->name('treasurers.index');
        Route::post('/treasurers', [TreasurerController::class, 'store'])->name('treasurers.store');
        Route::put('/treasurers/{id}', [TreasurerController::class, 'update'])->name('treasurers.update');
        Route::delete('/treasurers/{id}', [TreasurerController::class, 'destroy'])->name('treasurers.destroy');

        // CSV Import
        Route::post('/import-csv', [ImportCsvController::class, 'import'])->middleware('throttle:uploads')->name('import.csv');
    });

/*
|--------------------------------------------------------------------------
| Other Route Files
|--------------------------------------------------------------------------
*/

require __DIR__.'/instructor.php';
require __DIR__.'/portal.php';
Route::middleware(['auth:admin,instructor,office,registrar,treasurer,student,web', 'no.history'])->group(function () {
    Route::get('/notifications/api', [NotificationsApiController::class, 'index'])->name('notifications.api');
    Route::post('/notifications/api/read-all', [NotificationsApiController::class, 'markAllRead'])->name('notifications.api.readAll');
    Route::delete('/notifications/api/{notification}', [NotificationsApiController::class, 'destroy'])
        ->whereNumber('notification')
        ->name('notifications.api.destroy');
});

require __DIR__.'/office_treasurer.php';
