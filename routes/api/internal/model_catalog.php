<?php

use App\Http\Controllers\Api\Internal\V1\Ai\ModelCatalogController;
use App\Http\Controllers\Api\Internal\V1\Ai\ModelTrialController;
use App\Http\Controllers\Api\Internal\V1\Ai\WorkspaceModelSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    Route::get('model-catalog', [ModelCatalogController::class, 'index'])->name('model-catalog.index');
});

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}')
    ->group(function () {
        Route::get('model-settings', [WorkspaceModelSettingsController::class, 'show'])->name('model-settings.show');
        Route::put('model-settings', [WorkspaceModelSettingsController::class, 'update'])->name('model-settings.update');
        Route::post('model-catalog/{slug}/try', ModelTrialController::class)->middleware('throttle:model-trials')->name('model-catalog.try');
    });
