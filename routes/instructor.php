<?php

use App\Http\Controllers\Instructors\AuthController;
use App\Http\Controllers\Instructors\ChatController;
use App\Http\Controllers\Instructors\ClearanceController;
use App\Http\Controllers\Instructors\DashboardController;
use App\Http\Controllers\Instructors\ESignatureController;
use App\Http\Controllers\Instructors\SubmissionController;
use Illuminate\Support\Facades\Route;

// The controller handles an existing instructor session and redirects it to
// instructor.dashboard. Avoid Laravel's generic guest redirect, which resolves
// the unprefixed "dashboard" route and incorrectly lands in the Main Admin portal.
Route::prefix('instructor')->name('instructor.')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.submit');
    Route::post('login-code', [AuthController::class, 'verifyLoginCode'])
        ->middleware('throttle:otp-verify')->name('login.otp.verify');
    Route::post('login-code/resend', [AuthController::class, 'resendLoginCode'])
        ->middleware('throttle:otp-send')->name('login.otp.resend');
    Route::post('login-code/cancel', [AuthController::class, 'cancelLoginCode'])->name('login.otp.cancel');
});

// ── Auth-only ──
Route::middleware(['instructor.auth', 'no.history'])->prefix('instructor')->name('instructor.')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('clearance', [DashboardController::class, 'clearance'])->name('clearance');
    Route::post('remarks', [DashboardController::class, 'sendRemark'])->name('remarks.send');
    Route::delete('submissions/{submission}', [DashboardController::class, 'deleteSubmission'])->name('submissions.delete');
    Route::put('account', [DashboardController::class, 'updateAccount'])->name('account.update');

    Route::post('clearance/approve', [ClearanceController::class, 'approve'])->name('clearance.approve');
    Route::post('clearance/pending', [ClearanceController::class, 'pending'])->name('clearance.pending');
    Route::post('clearance/bulk', [ClearanceController::class, 'bulkUpdate'])->name('clearance.bulk');

    Route::get('submissions', [SubmissionController::class, 'index'])->name('submissions.index');
    Route::post('submissions/clearance', [SubmissionController::class, 'setClearance'])->name('submissions.clearance');
    Route::post('remarks/{remark}', [SubmissionController::class, 'editRemark'])->name('remarks.edit');
    Route::get('remarks', [SubmissionController::class, 'remarksHistory'])->name('remarks.history');
    Route::get('submissions/{submission}/download', [SubmissionController::class, 'download'])->name('submissions.download');

    Route::get('chat', [ChatController::class, 'index'])->name('chat');
    Route::post('chat/messages', [ChatController::class, 'send'])->name('chat.send');
    Route::get('chat/messages', [ChatController::class, 'messages'])->name('chat.messages');
    Route::delete('chat/messages/{message}', [ChatController::class, 'destroy'])->whereNumber('message')->name('chat.destroy');
});

// ── Shared e-signature endpoint (instructor, admin, registrar all use this guard-agnostic controller) ──
Route::middleware(['auth:instructor,admin,registrar', 'no.history'])->group(function () {
    Route::get('esignature', [ESignatureController::class, 'get'])->name('esignature.get');
    Route::post('esignature', [ESignatureController::class, 'save'])->middleware('throttle:uploads')->name('esignature.save');
    Route::delete('esignature', [ESignatureController::class, 'delete'])->name('esignature.delete');
});
