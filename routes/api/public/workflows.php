<?php

use App\Http\Controllers\Api\Public\V1\Workflows\WorkflowController;
use App\Http\Controllers\Api\Public\V1\Workflows\WorkflowGraphController;
use App\Http\Controllers\Api\Public\V1\Workflows\WorkflowInterfaceController;
use Illuminate\Support\Facades\Route;

Route::middleware('api-key:workflows:read')->group(function () {
    Route::get('workflows', [WorkflowController::class, 'index']);
    Route::get('workflows/{workflow}', [WorkflowController::class, 'show']);
    Route::get('workflows/{workflow}/graph', [WorkflowGraphController::class, 'show']);

    // The input contract a caller must satisfy to start this workflow.
    Route::get('workflows/{workflow}/interface', [WorkflowInterfaceController::class, 'show']);
});

/*
 * Programmatic authoring. Draft edits and publishing are separate calls for
 * the same reason they are in the editor: a draft may be mid-edit, a
 * published version may not: see WorkflowGraphController.
 */
Route::middleware('api-key:workflows:write')->group(function () {
    Route::post('workflows', [WorkflowController::class, 'store']);
    Route::patch('workflows/{workflow}', [WorkflowController::class, 'update']);
    Route::delete('workflows/{workflow}', [WorkflowController::class, 'destroy']);

    Route::put('workflows/{workflow}/graph', [WorkflowGraphController::class, 'replace']);
    Route::post('workflows/{workflow}/graph/validate', [WorkflowGraphController::class, 'validateGraph']);
    Route::post('workflows/{workflow}/versions', [WorkflowGraphController::class, 'publish']);
});
