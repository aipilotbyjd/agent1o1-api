<?php

use App\Http\Controllers\Api\Internal\V1\Triggers\TriggerController;
use App\Http\Controllers\Api\Internal\V1\Triggers\TriggerPresetController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    Route::get('catalog/trigger-presets', TriggerPresetController::class)->name('catalog.trigger-presets');

    Route::middleware('workspace.context')->prefix('workspaces/{workspace}/triggers')->as('triggers.')->group(function () {
        Route::get('/', [TriggerController::class, 'index'])->name('index');
        Route::post('/', [TriggerController::class, 'store'])->name('store');
        Route::patch('{trigger}', [TriggerController::class, 'update'])->name('update');
        Route::delete('{trigger}', [TriggerController::class, 'destroy'])->name('destroy');
        Route::post('{trigger}/run', [TriggerController::class, 'run'])->name('run');
        Route::get('{trigger}/sample', [TriggerController::class, 'sample'])->name('sample');
        Route::post('{trigger}/events/{event}/replay', [TriggerController::class, 'replay'])->name('events.replay');
        Route::post('{trigger}/rotate-token', [TriggerController::class, 'rotateToken'])->name('rotate-token');
        Route::get('{trigger}/events', [TriggerController::class, 'events'])->name('events.index');
    });
});
