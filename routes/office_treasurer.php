<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Office\AuthController as OfficeAuthController;
use App\Http\Controllers\Office\ChatController as OfficeChatController;
use App\Http\Controllers\Office\DashboardController as OfficeDashboardController;
use App\Http\Controllers\Office\GuidanceEvaluationController;
use App\Http\Controllers\Office\LibraryEvaluationController;
use App\Http\Controllers\Treasurer\AuthController as TreasurerAuthController;
use App\Http\Controllers\Treasurer\ChatController as TreasurerChatController;
use App\Http\Controllers\Treasurer\DashboardController as TreasurerDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Office Portal
|--------------------------------------------------------------------------
*/
Route::prefix('office')->name('office.')->group(function () {
    Route::get('login', [OfficeAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [OfficeAuthController::class, 'login'])->middleware('throttle:login')->name('login.submit');
    Route::post('login-code', [OfficeAuthController::class, 'verifyLoginCode'])
        ->middleware('throttle:otp-verify')->name('login.otp.verify');
    Route::post('login-code/resend', [OfficeAuthController::class, 'resendLoginCode'])
        ->middleware('throttle:otp-send')->name('login.otp.resend');
    Route::post('login-code/cancel', [OfficeAuthController::class, 'cancelLoginCode'])->name('login.otp.cancel');
    Route::post('logout', [OfficeAuthController::class, 'logout'])->middleware('office.auth', 'no.history')->name('logout');

    Route::middleware(['office.auth', 'no.history'])->group(function () {
        Route::get('dashboard', [OfficeDashboardController::class, 'index'])->name('dashboard');
        Route::get('library-evaluations', [LibraryEvaluationController::class, 'index'])->name('library-evaluations.index');
        Route::get('library-evaluations/create', [LibraryEvaluationController::class, 'create'])->name('library-evaluations.create');
        Route::post('library-evaluations', [LibraryEvaluationController::class, 'store'])->name('library-evaluations.store');
        Route::get('library-evaluations/{evaluation}/edit', [LibraryEvaluationController::class, 'edit'])->whereNumber('evaluation')->name('library-evaluations.edit');
        Route::put('library-evaluations/{evaluation}', [LibraryEvaluationController::class, 'update'])->whereNumber('evaluation')->name('library-evaluations.update');
        Route::delete('library-evaluations/{evaluation}', [LibraryEvaluationController::class, 'destroy'])->whereNumber('evaluation')->name('library-evaluations.destroy');
        Route::get('library-evaluations/{evaluation}/responses.csv', [LibraryEvaluationController::class, 'export'])->whereNumber('evaluation')->name('library-evaluations.export');
        Route::post('library-evaluations/{evaluation}/publish', [LibraryEvaluationController::class, 'publish'])->whereNumber('evaluation')->name('library-evaluations.publish');
        Route::get('library-evaluation-responses/{response}', [LibraryEvaluationController::class, 'showResponse'])->whereNumber('response')->name('library-evaluations.response');
        Route::get('guidance-evaluations', [GuidanceEvaluationController::class, 'index'])->name('guidance-evaluations.index');
        Route::get('guidance-evaluations/create', [GuidanceEvaluationController::class, 'create'])->name('guidance-evaluations.create');
        Route::post('guidance-evaluations', [GuidanceEvaluationController::class, 'store'])->name('guidance-evaluations.store');
        Route::get('guidance-evaluations/{evaluation}/edit', [GuidanceEvaluationController::class, 'edit'])->whereNumber('evaluation')->name('guidance-evaluations.edit');
        Route::put('guidance-evaluations/{evaluation}', [GuidanceEvaluationController::class, 'update'])->whereNumber('evaluation')->name('guidance-evaluations.update');
        Route::delete('guidance-evaluations/{evaluation}', [GuidanceEvaluationController::class, 'destroy'])->whereNumber('evaluation')->name('guidance-evaluations.destroy');
        Route::get('guidance-evaluations/{evaluation}/responses.csv', [GuidanceEvaluationController::class, 'export'])->whereNumber('evaluation')->name('guidance-evaluations.export');
        Route::post('guidance-evaluations/{evaluation}/publish', [GuidanceEvaluationController::class, 'publish'])->whereNumber('evaluation')->name('guidance-evaluations.publish');
        Route::get('guidance-evaluation-responses/{response}', [GuidanceEvaluationController::class, 'showResponse'])->whereNumber('response')->name('guidance-evaluations.response');
        Route::get('submissions', [OfficeDashboardController::class, 'submissions'])->name('submissions');
        Route::get('submissions/{submission}/file', [OfficeDashboardController::class, 'viewSubmissionFile'])->name('submissions.file');
        Route::get('clearance-requests', [OfficeDashboardController::class, 'clearanceRequests'])->name('clearance.requests');
        Route::post('clearance/status', [OfficeDashboardController::class, 'setClearanceStatus'])->name('clearance.status');
        Route::post('clearance/bulk-status', [OfficeDashboardController::class, 'bulkSetClearanceStatus'])->name('clearance.bulk-status');
        Route::get('chat', [OfficeChatController::class, 'index'])->name('chat');
        Route::get('chat/messages', [OfficeChatController::class, 'messages'])->name('chat.messages');
        Route::post('chat/messages', [OfficeChatController::class, 'send'])->name('chat.send');
        Route::delete('chat/messages/{message}', [OfficeChatController::class, 'destroy'])->whereNumber('message')->name('chat.destroy');
        Route::get('account/edit', [AccountController::class, 'edit'])->name('account.edit');
        Route::put('account', [AccountController::class, 'update'])->name('account.update');
    });
});

/*
|--------------------------------------------------------------------------
| Treasurer Portal
|--------------------------------------------------------------------------
*/
Route::prefix('treasurer')->name('treasurer.')->group(function () {
    Route::get('login', [TreasurerAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [TreasurerAuthController::class, 'login'])->middleware('throttle:login')->name('login.submit');
    Route::post('login-code', [TreasurerAuthController::class, 'verifyLoginCode'])
        ->middleware('throttle:otp-verify')->name('login.otp.verify');
    Route::post('login-code/resend', [TreasurerAuthController::class, 'resendLoginCode'])
        ->middleware('throttle:otp-send')->name('login.otp.resend');
    Route::post('login-code/cancel', [TreasurerAuthController::class, 'cancelLoginCode'])->name('login.otp.cancel');
    Route::post('logout', [TreasurerAuthController::class, 'logout'])->middleware('treasurer.auth', 'no.history')->name('logout');

    Route::middleware(['treasurer.auth', 'no.history'])->group(function () {
        Route::get('dashboard', [TreasurerDashboardController::class, 'index'])->name('dashboard');
        Route::get('clearance-updates', [TreasurerDashboardController::class, 'clearanceUpdates'])->name('clearance-updates');
        Route::post('clearance/status', [TreasurerDashboardController::class, 'setClearanceStatus'])->name('clearance.status');
        Route::post('clearance/bulk-status', [TreasurerDashboardController::class, 'bulkSetClearanceStatus'])->name('clearance.bulk-status');
        Route::get('submission-remark', [TreasurerDashboardController::class, 'submissionRemark'])->name('submission-remark');
        Route::get('submission-remark/{submission}/file', [TreasurerDashboardController::class, 'viewSubmissionFile'])->name('submission-remark.file');
        Route::get('chat', [TreasurerChatController::class, 'index'])->name('chat');
        Route::get('chat/messages', [TreasurerChatController::class, 'messages'])->name('chat.messages');
        Route::post('chat/messages', [TreasurerChatController::class, 'send'])->name('chat.send');
        Route::delete('chat/messages/{message}', [TreasurerChatController::class, 'destroy'])->whereNumber('message')->name('chat.destroy');
        Route::get('account/edit', [AccountController::class, 'edit'])->name('account.edit');
        Route::put('account', [AccountController::class, 'update'])->name('account.update');
    });
});
