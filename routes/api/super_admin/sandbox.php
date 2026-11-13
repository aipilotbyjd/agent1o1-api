<?php

use App\Http\Controllers\Api\SuperAdmin\V1\Sandbox\SuperAdminSandboxController;
use Illuminate\Support\Facades\Route;

/*
| Where code runs: the providers (hosted sandboxes, or runners on separate
| machines), which one is tried first, the kill switch, the monthly budget
| and the cost.
|
| Reading needs `sandbox.view` (every role); changing anything needs
| `sandbox.manage` plus a fresh authenticator code (see the form requests).
| No provider runs on this server: every address is checked to be another,
| public machine (`SandboxUrlGuard`).
*/
$base = [
    'throttle:super-admin', 'auth:super-admin', 'super-admin.ip:account', 'super-admin.active',
    'super-admin.two-factor', 'super-admin.audit',
];

Route::prefix('sandbox')->as('sandbox.')->group(function () use ($base) {
    Route::middleware([...$base, 'super-admin.can:sandbox.view'])->group(function () {
        Route::get('/', [SuperAdminSandboxController::class, 'overview'])->name('overview');
        Route::get('providers', [SuperAdminSandboxController::class, 'index'])->name('providers.index');
        Route::get('usage', [SuperAdminSandboxController::class, 'usage'])->name('usage');
    });

    Route::middleware([...$base, 'super-admin.can:sandbox.manage', 'throttle:super-admin-sensitive'])->group(function () {
        Route::post('providers', [SuperAdminSandboxController::class, 'store'])->name('providers.store');
        Route::patch('providers/{provider}', [SuperAdminSandboxController::class, 'update'])->whereUuid('provider')->name('providers.update');
        Route::delete('providers/{provider}', [SuperAdminSandboxController::class, 'destroy'])->whereUuid('provider')->name('providers.destroy');
        Route::post('providers/{key}/test', [SuperAdminSandboxController::class, 'test'])->where('key', '[a-z0-9-]+')->name('providers.test');
        Route::post('default', [SuperAdminSandboxController::class, 'setDefault'])->name('default');
        Route::put('order', [SuperAdminSandboxController::class, 'reorder'])->name('order');
        Route::patch('settings', [SuperAdminSandboxController::class, 'updateSettings'])->name('settings');
    });
});
