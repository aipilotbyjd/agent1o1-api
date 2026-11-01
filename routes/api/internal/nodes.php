<?php

use App\Http\Controllers\Api\Internal\V1\Nodes\NodeCategoryController;
use App\Http\Controllers\Api\Internal\V1\Nodes\NodeController;
use App\Http\Controllers\Api\Internal\V1\Nodes\NodeOptionsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    Route::get('node-categories', [NodeCategoryController::class, 'index'])->name('node-categories.index');
    Route::get('node-categories/{nodeCategory}', [NodeCategoryController::class, 'show'])->name('node-categories.show');
    Route::patch('node-categories/{nodeCategory}/pin', [NodeCategoryController::class, 'pin'])->name('node-categories.pin');

    Route::get('nodes', [NodeController::class, 'globalCatalog'])->name('nodes.global-catalog');
});

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/nodes')
    ->as('nodes.')
    ->group(function () {
        Route::get('custom', [NodeController::class, 'custom'])->name('custom');
        Route::get('recently-used', [NodeController::class, 'recentlyUsed'])->name('recently-used');
        Route::post('options', NodeOptionsController::class)->middleware('throttle:120,1')->name('options');

        Route::get('/', [NodeController::class, 'index'])->name('index');
        Route::post('/', [NodeController::class, 'store'])->name('store');
        Route::get('{node}', [NodeController::class, 'show'])->name('show');
        Route::patch('{node}', [NodeController::class, 'update'])->name('update');
        Route::delete('{node}', [NodeController::class, 'destroy'])->name('destroy');
    });
