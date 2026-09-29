<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ClearanceFormController;
use App\Http\Controllers\Registrar\AuthController as RegistrarAuthController;
use App\Http\Controllers\Registrar\ChatController as RegistrarChatController;
use App\Http\Controllers\Registrar\DashboardController as RegistrarDashboardController;
use App\Http\Controllers\Registrar\StudentClearanceController as RegistrarStudentClearanceController;
use App\Http\Controllers\Student\ApplicationDownloadController;
use App\Http\Controllers\Student\AuthController as StudentAuthController;
use App\Http\Controllers\Student\ChatSupportController;
use App\Http\Controllers\Student\ClearanceUpdatesController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\GuidanceEvaluationController as StudentGuidanceEvaluationController;
use App\Http\Controllers\Student\LibraryEvaluationController as StudentLibraryEvaluationController;
use App\Http\Controllers\Student\RegistrationController as StudentRegistrationController;
use App\Http\Controllers\Student\SubjectEnrollmentController;
use App\Http\Controllers\Student\SubmissionRemarkController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Student Portal
|--------------------------------------------------------------------------
*/
Route::prefix('student')->name('student.')->group(function () {
    Route::get('/', function () {
        return redirect()->route('student.login');
    });
    Route::get('login', [StudentAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [StudentAuthController::class, 'login'])->middleware('throttle:login')->name('login.submit');
    Route::post('login-code', [StudentAuthController::class, 'verifyLoginCode'])
        ->middleware('throttle:otp-verify')->name('login.otp.verify');
    Route::post('login-code/resend', [StudentAuthController::class, 'resendLoginCode'])
        ->middleware('throttle:otp-send')->name('login.otp.resend');
    Route::post('login-code/cancel', [StudentAuthController::class, 'cancelLoginCode'])->name('login.otp.cancel');
    Route::post('password-recovery/send-code', [StudentAuthController::class, 'sendRecoveryCode'])
        ->middleware('throttle:otp-send')->name('password-recovery.send-code');
    Route::post('password-recovery/verify-code', [StudentAuthController::class, 'verifyRecoveryCode'])
        ->middleware('throttle:otp-verify')->name('password-recovery.verify-code');
    Route::post('password-recovery/reset', [StudentAuthController::class, 'resetRecoveredPassword'])
        ->middleware('throttle:password-reset')->name('password-recovery.reset');
    Route::post('password-recovery/cancel', [StudentAuthController::class, 'cancelPasswordRecovery'])
        ->name('password-recovery.cancel');

    // Self-registration, gated on the student_registry roster.
    Route::post('register/send-code', [StudentRegistrationController::class, 'sendCode'])
        ->middleware('throttle:otp-send')->name('register.send-code');
    Route::post('register/verify-code', [StudentRegistrationController::class, 'verifyCode'])
        ->middleware('throttle:otp-verify')->name('register.verify-code');
    Route::get('register', [StudentRegistrationController::class, 'showForm'])->name('register');
    Route::post('register', [StudentRegistrationController::class, 'register'])
        ->middleware('throttle:uploads')->name('register.submit');
    Route::post('register/cancel', [StudentRegistrationController::class, 'cancel'])->name('register.cancel');
    Route::post('logout', [StudentAuthController::class, 'logout'])->middleware('student.auth', 'no.history')->name('logout');

    Route::middleware(['student.auth', 'no.history'])->group(function () {
        Route::get('dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
        Route::get('application/download', ApplicationDownloadController::class)->name('application.download');
        Route::get('clearance-form', [ClearanceFormController::class, 'student'])->name('clearance.form');
        Route::get('clearance-form/download', [ClearanceFormController::class, 'studentDownload'])->name('clearance.form.download');
        Route::get('clearance-updates', [ClearanceUpdatesController::class, 'index'])->name('clearance-updates');
        Route::get('library-evaluation', [StudentLibraryEvaluationController::class, 'index'])->name('library-evaluation.index');
        Route::post('library-evaluation', [StudentLibraryEvaluationController::class, 'store'])->name('library-evaluation.store');
        Route::get('guidance-evaluation', [StudentGuidanceEvaluationController::class, 'index'])->name('guidance-evaluation.index');
        Route::post('guidance-evaluation', [StudentGuidanceEvaluationController::class, 'store'])->name('guidance-evaluation.store');
        // Irregular students declare their own subjects and instructors.
        Route::get('my-subjects', [SubjectEnrollmentController::class, 'index'])->name('subjects.index');
        Route::post('my-subjects', [SubjectEnrollmentController::class, 'store'])->name('subjects.store');
        Route::delete('my-subjects', [SubjectEnrollmentController::class, 'destroy'])->name('subjects.destroy');
        Route::post('my-subjects/bridging', [SubjectEnrollmentController::class, 'storeBridging'])->name('subjects.bridging.store');
        Route::delete('my-subjects/bridging', [SubjectEnrollmentController::class, 'destroyBridging'])->name('subjects.bridging.destroy');
        Route::post('clearance/submit-instructor', [ClearanceUpdatesController::class, 'submitInstructor'])->name('clearance.submit-instructor');
        Route::post('clearance/submit-office', [ClearanceUpdatesController::class, 'submitOffice'])->name('clearance.submit-office');
        Route::post('clearance/upload-office', [ClearanceUpdatesController::class, 'uploadOfficeSubmission'])->middleware('throttle:uploads')->name('clearance.upload-office');
        Route::get('clearance/office-submission/{submission}', [ClearanceUpdatesController::class, 'viewOfficeSubmission'])->whereNumber('submission')->name('clearance.office-submission');
        Route::get('submission-remark', [SubmissionRemarkController::class, 'index'])->name('submission-remark');
        Route::post('submission-remark/upload', [SubmissionRemarkController::class, 'upload'])->middleware('throttle:uploads')->name('submission-remark.upload');
        Route::get('submission-remark/download/{submission}', [SubmissionRemarkController::class, 'download'])->name('submission-remark.download');
        Route::get('chat-support', [ChatSupportController::class, 'index'])->name('chat-support');
        Route::get('chat-support/messages', [ChatSupportController::class, 'messages'])->name('chat.messages');
        Route::post('chat-support/messages', [ChatSupportController::class, 'send'])->name('chat.send');
        Route::delete('chat-support/messages/{message}', [ChatSupportController::class, 'destroy'])->whereNumber('message')->name('chat.destroy');
        Route::get('account/edit', [AccountController::class, 'edit'])->name('account.edit');
        Route::put('account', [AccountController::class, 'update'])->name('account.update');
    });
});

/*
|--------------------------------------------------------------------------
| Registrar Portal
|--------------------------------------------------------------------------
*/
Route::prefix('registrar')->name('registrar.')->group(function () {
    Route::get('/', function () {
        return redirect()->route('registrar.login');
    });
    Route::get('login', [RegistrarAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [RegistrarAuthController::class, 'login'])->middleware('throttle:login')->name('login.submit');
    Route::post('login-code', [RegistrarAuthController::class, 'verifyLoginCode'])
        ->middleware('throttle:otp-verify')->name('login.otp.verify');
    Route::post('login-code/resend', [RegistrarAuthController::class, 'resendLoginCode'])
        ->middleware('throttle:otp-send')->name('login.otp.resend');
    Route::post('login-code/cancel', [RegistrarAuthController::class, 'cancelLoginCode'])->name('login.otp.cancel');
    Route::post('logout', [RegistrarAuthController::class, 'logout'])->middleware('registrar.auth', 'no.history')->name('logout');

    Route::middleware(['registrar.auth', 'no.history'])->group(function () {
        Route::get('dashboard', [RegistrarDashboardController::class, 'index'])->name('dashboard');
        Route::get('clearance-form/{studentId}', [ClearanceFormController::class, 'registrar'])->name('clearance.form');
        Route::get('clearance/verify/{studentId}', [ClearanceFormController::class, 'verify'])
            ->middleware('signed')->name('clearance.verify');
        Route::get('student-clearance', [RegistrarStudentClearanceController::class, 'index'])->name('student-clearance');
        Route::get('qr-scanner', [RegistrarStudentClearanceController::class, 'scanner'])->name('qr-scanner');
        Route::post('student-clearance/status', [RegistrarStudentClearanceController::class, 'setClearanceStatus'])->name('student-clearance.status');
        Route::post('student-clearance/bulk-status', [RegistrarStudentClearanceController::class, 'bulkSetClearanceStatus'])->name('student-clearance.bulk-status');
        Route::get('chat', [RegistrarChatController::class, 'index'])->name('chat');
        Route::get('chat/messages', [RegistrarChatController::class, 'messages'])->name('chat.messages');
        Route::post('chat/messages', [RegistrarChatController::class, 'send'])->name('chat.send');
        Route::delete('chat/messages/{message}', [RegistrarChatController::class, 'destroy'])->whereNumber('message')->name('chat.destroy');
        Route::get('account/edit', [AccountController::class, 'edit'])->name('account.edit');
        Route::put('account', [AccountController::class, 'update'])->name('account.update');
    });
});
