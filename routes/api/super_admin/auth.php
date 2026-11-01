<?php

use App\Http\Controllers\Api\SuperAdmin\V1\Auth\AuthController;
use App\Http\Controllers\Api\SuperAdmin\V1\Auth\TwoFactorController;
use Illuminate\Support\Facades\Route;

/*
| Authenticated, but a "setup" token is enough (and is all a super admin
| without two-factor authentication ever holds).
*/
$authenticated = ['throttle:super-admin', 'auth:super-admin', 'super-admin.ip:account', 'super-admin.active'];

/*
| Everything else needs a full token: only issued after a two-factor code.
*/
$full = [...$authenticated, 'super-admin.two-factor', 'super-admin.audit'];

Route::prefix('auth')->as('auth.')->group(function () use ($authenticated, $full) {
    Route::middleware('throttle:super-admin-auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('2fa/verify', [AuthController::class, 'verifyTwoFactor'])->name('2fa.verify');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
    });

    Route::middleware($authenticated)->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        Route::post('change-password', [AuthController::class, 'changePassword'])->name('change-password');

        Route::post('2fa/enable', [TwoFactorController::class, 'enable'])->name('2fa.enable');
        Route::post('2fa/confirm', [TwoFactorController::class, 'confirm'])->name('2fa.confirm');
    });

    Route::middleware($full)->group(function () {
        Route::post('2fa/recovery-codes/regenerate', [TwoFactorController::class, 'regenerateRecoveryCodes'])
            ->middleware('throttle:super-admin-sensitive')
            ->name('2fa.recovery-codes.regenerate');

        Route::get('sessions', [AuthController::class, 'sessions'])->name('sessions.index');
        Route::delete('sessions/{token}', [AuthController::class, 'revokeSession'])->whereUuid('token')->name('sessions.destroy');
        Route::get('events', [AuthController::class, 'events'])->name('events.index');
    });
});
