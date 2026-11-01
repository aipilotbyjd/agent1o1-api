<?php

use App\Http\Controllers\Api\SuperAdmin\V1\Runs\SuperAdminRunController;
use Illuminate\Support\Facades\Route;

$base = [
    'throttle:super-admin', 'auth:super-admin', 'super-admin.ip:account', 'super-admin.active',
    'super-admin.two-factor', 'super-admin.audit',
];

Route::prefix('runs')->as('runs.')->group(function () use ($base) {
    Route::middleware([...$base, 'super-admin.can:runs.view'])->group(function () {
        Route::get('/', [SuperAdminRunController::class, 'index'])->name('index');
        Route::get('failures/summary', [SuperAdminRunController::class, 'failureSummary'])->name('failures.summary');
        Route::get('{run}', [SuperAdminRunController::class, 'show'])->whereUuid('run')->name('show');
        Route::get('{run}/node-runs', [SuperAdminRunController::class, 'nodeRuns'])->whereUuid('run')->name('node-runs');
    });

    Route::post('{run}/retry', [SuperAdminRunController::class, 'retry'])
        ->middleware([...$base, 'super-admin.can:runs.retry'])
        ->whereUuid('run')
        ->name('retry');

    Route::post('{run}/cancel', [SuperAdminRunController::class, 'cancel'])
        ->middleware([...$base, 'super-admin.can:runs.cancel'])
        ->whereUuid('run')
        ->name('cancel');
});
