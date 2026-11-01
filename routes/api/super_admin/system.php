<?php

use App\Http\Controllers\Api\SuperAdmin\V1\System\SystemFailedJobController;
use App\Http\Controllers\Api\SuperAdmin\V1\System\SystemHealthController;
use App\Http\Controllers\Api\SuperAdmin\V1\System\SystemMaintenanceController;
use Illuminate\Support\Facades\Route;

$base = [
    'throttle:super-admin', 'auth:super-admin', 'super-admin.ip:account', 'super-admin.active',
    'super-admin.two-factor', 'super-admin.audit',
];

Route::prefix('system')->as('system.')->group(function () use ($base) {
    Route::middleware([...$base, 'super-admin.can:system.view'])->group(function () {
        Route::get('health', [SystemHealthController::class, 'health'])->name('health');
        Route::get('services', [SystemHealthController::class, 'services'])->name('services');
        Route::get('queues', [SystemHealthController::class, 'queues'])->name('queues');
        Route::get('scheduler', [SystemHealthController::class, 'scheduler'])->name('scheduler');
        Route::get('horizon', [SystemHealthController::class, 'horizon'])->name('horizon');
        Route::get('stuck', [SystemHealthController::class, 'stuck'])->name('stuck');
        Route::get('history', [SystemHealthController::class, 'history'])->name('history');

        Route::get('failed-jobs', [SystemFailedJobController::class, 'index'])->name('failed-jobs.index');
        Route::get('failed-jobs/summary', [SystemFailedJobController::class, 'summary'])->name('failed-jobs.summary');
        Route::get('failed-jobs/{id}', [SystemFailedJobController::class, 'show'])->whereUuid('id')->name('failed-jobs.show');
    });

    Route::middleware([...$base, 'super-admin.can:system.manage', 'throttle:super-admin-sensitive'])->group(function () {
        Route::post('failed-jobs/retry', [SystemFailedJobController::class, 'retryMany'])->name('failed-jobs.retry-many');
        Route::post('failed-jobs/flush', [SystemFailedJobController::class, 'flush'])->name('failed-jobs.flush');
        Route::post('failed-jobs/{id}/retry', [SystemFailedJobController::class, 'retry'])->whereUuid('id')->name('failed-jobs.retry');
        Route::delete('failed-jobs/{id}', [SystemFailedJobController::class, 'destroy'])->whereUuid('id')->name('failed-jobs.destroy');

        Route::post('maintenance/horizon/pause', [SystemMaintenanceController::class, 'pauseHorizon'])->name('maintenance.horizon.pause');
        Route::post('maintenance/horizon/continue', [SystemMaintenanceController::class, 'continueHorizon'])->name('maintenance.horizon.continue');
        Route::post('maintenance/workers/restart', [SystemMaintenanceController::class, 'restartWorkers'])->name('maintenance.workers.restart');
    });
});
