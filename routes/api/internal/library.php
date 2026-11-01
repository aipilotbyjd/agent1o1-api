<?php

use App\Http\Controllers\Api\Internal\V1\Library\LibraryController;
use Illuminate\Support\Facades\Route;

Route::get('library/{artifact}/view', [LibraryController::class, 'view'])
    ->middleware('signed')
    ->name('library.view');

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/library')
    ->as('library.')
    ->group(function () {
        Route::get('/', [LibraryController::class, 'index'])->name('index');
        Route::get('{artifact}/download', [LibraryController::class, 'download'])->name('download');
    });
