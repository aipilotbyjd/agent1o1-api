<?php

use App\Http\Controllers\Api\Internal\V1\Artifacts\ArtifactController;
use Illuminate\Support\Facades\Route;

Route::get('artifacts/{artifact}/preview', [ArtifactController::class, 'preview'])
    ->middleware('signed')
    ->name('artifacts.preview');

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/artifacts')
    ->as('artifacts.')
    ->group(function () {
        Route::get('/', [ArtifactController::class, 'index'])->name('index');
        Route::post('/', [ArtifactController::class, 'store'])->name('store');
        Route::get('{artifact}', [ArtifactController::class, 'show'])->name('show');
        Route::delete('{artifact}', [ArtifactController::class, 'destroy'])->name('destroy');
        Route::get('{artifact}/download', [ArtifactController::class, 'download'])->name('download');
        Route::patch('{artifact}/access', [ArtifactController::class, 'updateAccess'])->name('access.update');
        Route::post('{artifact}/shares', [ArtifactController::class, 'addShare'])->name('shares.store');
        Route::delete('{artifact}/shares/{user}', [ArtifactController::class, 'removeShare'])->name('shares.destroy');
    });
