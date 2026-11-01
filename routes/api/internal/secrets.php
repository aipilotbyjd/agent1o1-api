<?php

use App\Http\Controllers\Api\Internal\V1\Secrets\SecretController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/secrets')
    ->as('secrets.')
    ->group(function () {
        Route::get('/', [SecretController::class, 'index'])->name('index');
        Route::post('/', [SecretController::class, 'store'])->name('store');
        Route::get('{secret}', [SecretController::class, 'show'])->name('show');
        Route::patch('{secret}', [SecretController::class, 'update'])->name('update');
        Route::delete('{secret}', [SecretController::class, 'destroy'])->name('destroy');
    });
