<?php

use App\Http\Controllers\Api\Public\V1\Runs\NodeRunController;
use App\Http\Controllers\Api\Public\V1\Runs\RunController;
use Illuminate\Support\Facades\Route;

Route::middleware('api-key:runs:read')->group(function () {
    Route::get('runs', [RunController::class, 'index']);
    Route::get('runs/{run}', [RunController::class, 'show']);
    Route::get('runs/{run}/node-runs', [NodeRunController::class, 'index']);
    Route::get('runs/{run}/node-runs/{nodeRun}', [NodeRunController::class, 'show']);
});

/*
 * Starting, stopping and retrying a run all sit under `workflows:write`
 * rather than a scope of their own: `workflows:write` has meant "may cause
 * this workspace's workflows to execute" since the start-run endpoint
 * shipped, and splitting it now would silently downgrade existing keys.
 */
Route::middleware('api-key:workflows:write')->group(function () {
    Route::post('workflows/{workflow}/runs', [RunController::class, 'store']);
    Route::post('runs/{run}/cancel', [RunController::class, 'cancel']);
    Route::post('runs/{run}/retry', [RunController::class, 'retry']);
});
