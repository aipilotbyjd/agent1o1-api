<?php

use App\Http\Controllers\Api\SuperAdmin\V1\Users\SuperAdminUserController;
use Illuminate\Support\Facades\Route;

$base = [
    'throttle:super-admin', 'auth:super-admin', 'super-admin.ip:account', 'super-admin.active',
    'super-admin.two-factor', 'super-admin.audit',
];

Route::prefix('users')->as('users.')->group(function () use ($base) {
    Route::middleware([...$base, 'super-admin.can:users.view'])->group(function () {
        Route::get('/', [SuperAdminUserController::class, 'index'])->name('index');
        Route::get('{user}', [SuperAdminUserController::class, 'show'])->whereUuid('user')->name('show');
    });

    Route::middleware([...$base, 'super-admin.can:users.suspend'])->group(function () {
        Route::post('{user}/suspend', [SuperAdminUserController::class, 'suspend'])->whereUuid('user')->name('suspend');
        Route::post('{user}/unsuspend', [SuperAdminUserController::class, 'unsuspend'])->whereUuid('user')->name('unsuspend');

        Route::post('{user}/resend-verification', [SuperAdminUserController::class, 'resendVerification'])
            ->middleware('throttle:super-admin-sensitive')
            ->whereUuid('user')
            ->name('resend-verification');
    });

    Route::middleware([...$base, 'super-admin.can:users.force-logout'])->group(function () {
        Route::post('{user}/force-logout', [SuperAdminUserController::class, 'forceLogout'])->whereUuid('user')->name('force-logout');
    });
});
