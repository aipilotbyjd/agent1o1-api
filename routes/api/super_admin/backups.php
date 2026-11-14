<?php

use App\Http\Controllers\Api\SuperAdmin\V1\Backups\BackupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Backups
|--------------------------------------------------------------------------
|
| Backups are immutable: there is no update or delete route on purpose,
| and a test keeps it that way. Creating one and downloading one are
| separate abilities; downloading a database dump also asks for a reason
| and a fresh authenticator code.
|
*/

$base = [
    'throttle:super-admin', 'auth:super-admin', 'super-admin.ip:account', 'super-admin.active',
    'super-admin.two-factor', 'super-admin.audit',
];

Route::prefix('backups')->as('backups.')->group(function () use ($base) {
    Route::middleware([...$base, 'super-admin.can:backups.view'])->group(function () {
        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::get('summary', [BackupController::class, 'summary'])->name('summary');
        Route::get('{backup}', [BackupController::class, 'show'])->whereUuid('backup')->name('show');
    });

    Route::post('/', [BackupController::class, 'store'])
        ->middleware([...$base, 'super-admin.can:backups.run', 'throttle:super-admin-sensitive'])
        ->name('store');

    Route::post('{backup}/download-link', [BackupController::class, 'downloadLink'])
        ->middleware([...$base, 'super-admin.can:backups.download', 'throttle:super-admin-sensitive'])
        ->whereUuid('backup')
        ->name('download-link');
});
