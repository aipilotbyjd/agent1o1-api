<?php

use App\Http\Controllers\Api\SuperAdmin\V1\Accounts\SuperAdminAccountController;
use Illuminate\Support\Facades\Route;

/*
| Managing other super admins: owners only (`accounts.manage`). Every write
| takes a reason and a fresh authenticator code (see `StepUpRequest`).
*/
Route::middleware([
    'throttle:super-admin', 'auth:super-admin', 'super-admin.ip:account', 'super-admin.active',
    'super-admin.two-factor', 'super-admin.audit', 'super-admin.can:accounts.manage',
])->prefix('accounts')->as('accounts.')->group(function () {
    Route::get('/', [SuperAdminAccountController::class, 'index'])->name('index');
    Route::get('{account}', [SuperAdminAccountController::class, 'show'])->whereUuid('account')->name('show');
    Route::get('{account}/login-events', [SuperAdminAccountController::class, 'loginEvents'])->whereUuid('account')->name('login-events');

    Route::middleware('throttle:super-admin-sensitive')->group(function () {
        Route::post('/', [SuperAdminAccountController::class, 'store'])->name('store');
        Route::patch('{account}', [SuperAdminAccountController::class, 'update'])->whereUuid('account')->name('update');
        Route::post('{account}/role', [SuperAdminAccountController::class, 'changeRole'])->whereUuid('account')->name('role');
        Route::post('{account}/disable', [SuperAdminAccountController::class, 'disable'])->whereUuid('account')->name('disable');
        Route::post('{account}/enable', [SuperAdminAccountController::class, 'enable'])->whereUuid('account')->name('enable');
        Route::post('{account}/reset-2fa', [SuperAdminAccountController::class, 'resetTwoFactor'])->whereUuid('account')->name('reset-2fa');
        Route::post('{account}/revoke-sessions', [SuperAdminAccountController::class, 'revokeSessions'])->whereUuid('account')->name('revoke-sessions');
    });
});
