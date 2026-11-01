<?php

use App\Http\Controllers\Api\Internal\V1\Auth\ApiKeyController;
use App\Http\Controllers\Api\Internal\V1\Auth\AuthController;
use App\Http\Controllers\Api\Internal\V1\Auth\TwoFactorController;
use App\Http\Controllers\Api\Internal\V1\User\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->as('auth.')->middleware('throttle:auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->name('register');
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');

    Route::post('2fa/verify', [AuthController::class, 'verifyTwoFactor'])->name('2fa.verify');

    Route::get('social/{provider}/redirect', [AuthController::class, 'redirectToProvider'])
        ->where('provider', 'google|github')
        ->name('social.redirect');

    // Matched on POST as well as GET: a popup-based OAuth flow posts the
    // provider's code back rather than navigating to it.
    Route::match(['GET', 'POST'], 'social/{provider}/callback', [AuthController::class, 'handleProviderCallback'])
        ->where('provider', 'google|github')
        ->name('social.callback');

    Route::post('social/exchange', [AuthController::class, 'exchangeSocialCode'])->name('social.exchange');

    // Signature checked in the controller rather than by the `signed`
    // middleware: the middleware aborts with a bare 403, and these two links
    // are followed by a person in their email client who should land on the
    // frontend with a reason, not on an error page.
    Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->whereUuid('id')
        ->name('verify-email');

    // Signed rather than authenticated: the link is opened from the new mailbox,
    // which is usually not the browser holding the session.
    Route::get('confirm-email-change/{id}/{hash}', [AuthController::class, 'confirmEmailChange'])
        ->whereUuid('id')
        ->name('confirm-email-change');

    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        Route::post('change-password', [AuthController::class, 'changePassword'])->name('change-password');
        Route::post('resend-verification', [AuthController::class, 'resendVerification'])->name('resend-verification');

        Route::get('sessions', [AuthController::class, 'sessions'])->name('sessions.index');
        Route::delete('sessions/{tokenId}', [AuthController::class, 'revokeSession'])->name('sessions.destroy');
        Route::get('events', [AuthController::class, 'events'])->name('events.index');

        Route::prefix('2fa')->as('2fa.')->group(function () {
            Route::post('enable', [TwoFactorController::class, 'enable'])->name('enable');
            Route::post('confirm', [TwoFactorController::class, 'confirm'])->name('confirm');
            Route::post('disable', [TwoFactorController::class, 'disable'])->name('disable');
            Route::get('recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('recovery-codes');
            Route::post('recovery-codes/regenerate', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('recovery-codes.regenerate');
        });
    });
});

Route::middleware('auth:api')->group(function () {
    Route::prefix('user')->as('user.')->group(function () {
        Route::get('/', [UserController::class, 'show'])->name('show');
        Route::patch('/', [UserController::class, 'update'])->name('update');
        Route::delete('/', [UserController::class, 'destroy'])->name('destroy');
        Route::delete('pending-email', [UserController::class, 'cancelEmailChange'])->name('pending-email.destroy');
        Route::post('switch-workspace', [UserController::class, 'switchWorkspace'])->name('switch-workspace');
        Route::post('avatar', [UserController::class, 'uploadAvatar'])->name('avatar.store');
        Route::delete('avatar', [UserController::class, 'deleteAvatar'])->name('avatar.destroy');
    });

    Route::prefix('workspaces/{workspace}/api-keys')->as('api-keys.')->middleware('workspace.context')->group(function () {
        Route::get('/', [ApiKeyController::class, 'index'])->name('index');
        Route::post('/', [ApiKeyController::class, 'store'])->name('store');
        Route::delete('{apiKey}', [ApiKeyController::class, 'destroy'])->name('destroy');
    });
});
