<?php

use App\Http\Controllers\Api\SuperAdmin\V1\Workspaces\SuperAdminWorkspaceController;
use Illuminate\Support\Facades\Route;

$base = [
    'throttle:super-admin', 'auth:super-admin', 'super-admin.ip:account', 'super-admin.active',
    'super-admin.two-factor', 'super-admin.audit',
];

Route::prefix('workspaces')->as('workspaces.')->group(function () use ($base) {
    Route::middleware([...$base, 'super-admin.can:workspaces.view'])->group(function () {
        Route::get('/', [SuperAdminWorkspaceController::class, 'index'])->name('index');
        Route::get('{workspace}', [SuperAdminWorkspaceController::class, 'show'])->whereUuid('workspace')->name('show');
        Route::get('{workspace}/members', [SuperAdminWorkspaceController::class, 'members'])->whereUuid('workspace')->name('members');
    });

    Route::middleware([...$base, 'super-admin.can:workspaces.suspend'])->group(function () {
        Route::post('{workspace}/suspend', [SuperAdminWorkspaceController::class, 'suspend'])->whereUuid('workspace')->name('suspend');
        Route::post('{workspace}/unsuspend', [SuperAdminWorkspaceController::class, 'unsuspend'])->whereUuid('workspace')->name('unsuspend');

        Route::delete('{workspace}/members/{member}', [SuperAdminWorkspaceController::class, 'removeMember'])
            ->middleware('throttle:super-admin-sensitive')
            ->whereUuid(['workspace', 'member'])
            ->name('members.destroy');
    });
});
