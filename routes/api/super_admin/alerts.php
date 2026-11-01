<?php

use App\Http\Controllers\Api\SuperAdmin\V1\Alerts\AlertEventController;
use App\Http\Controllers\Api\SuperAdmin\V1\Alerts\AlertRuleController;
use Illuminate\Support\Facades\Route;

$base = [
    'throttle:super-admin', 'auth:super-admin', 'super-admin.ip:account', 'super-admin.active',
    'super-admin.two-factor', 'super-admin.audit',
];

Route::prefix('alerts')->as('alerts.')->group(function () use ($base) {
    Route::middleware([...$base, 'super-admin.can:alerts.view'])->group(function () {
        Route::get('/', [AlertEventController::class, 'index'])->name('index');
        Route::get('summary', [AlertEventController::class, 'summary'])->name('summary');
        Route::get('rules', [AlertRuleController::class, 'index'])->name('rules.index');
        Route::get('{alert}', [AlertEventController::class, 'show'])->whereUuid('alert')->name('show');
    });

    Route::middleware([...$base, 'super-admin.can:alerts.manage'])->group(function () {
        Route::post('{alert}/acknowledge', [AlertEventController::class, 'acknowledge'])->whereUuid('alert')->name('acknowledge');
        Route::post('{alert}/resolve', [AlertEventController::class, 'resolve'])->whereUuid('alert')->name('resolve');

        Route::patch('rules/{rule}', [AlertRuleController::class, 'update'])->whereUuid('rule')->name('rules.update');
        Route::post('rules/{rule}/snooze', [AlertRuleController::class, 'snooze'])->whereUuid('rule')->name('rules.snooze');
        Route::post('rules/{rule}/dry-run', [AlertRuleController::class, 'dryRun'])->whereUuid('rule')->name('rules.dry-run');
        Route::post('rules/{rule}/test', [AlertRuleController::class, 'test'])->whereUuid('rule')->middleware('throttle:super-admin-sensitive')->name('rules.test');
    });
});
