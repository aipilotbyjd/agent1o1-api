<?php

use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowBuilderAssistController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowBuilderController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowBuilderDraftVersionController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowBuilderMessageController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowBuilderSessionController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowDiagnosticsController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowNodeTestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'workspace.context'])->group(function () {
    // Editor-only concerns on an already-created Workflow (node placement,
    // edges, autosave): see WorkflowBuilderController.
    Route::prefix('workspaces/{workspace}/workflows')
        ->as('workflows.builder.')
        ->group(function () {
            Route::put('{workflow}/graph', [WorkflowBuilderController::class, 'replaceGraph'])->name('graph');

            // Pre-flight checks over the same services the builder agent's
            // own tools call: see WorkflowDiagnosticsController.
            Route::post('{workflow}/validate', [WorkflowDiagnosticsController::class, 'validateGraph'])->name('validate');
            Route::post('{workflow}/dry-run', [WorkflowDiagnosticsController::class, 'dryRun'])->name('dry-run');

            // Executes one node for real: see NodeTester.
            Route::post('{workflow}/nodes/{node}/test', [WorkflowNodeTestController::class, 'store'])->name('nodes.test');
        });

    // The chat-based builder: sessions own a draft_graph edited through
    // WorkflowBuilderAgent's tools (and the canvas, via PATCH draft),
    // promoted to a real Workflow when ready.
    Route::prefix('workspaces/{workspace}/workflow-builder-sessions')
        ->as('workflow-builder-sessions.')
        ->group(function () {
            Route::get('/', [WorkflowBuilderSessionController::class, 'index'])->name('index');
            Route::post('/', [WorkflowBuilderSessionController::class, 'store'])->name('store')->middleware('throttle:workflow-builder-messages');
            Route::get('{session}', [WorkflowBuilderSessionController::class, 'show'])->name('show');
            Route::patch('{session}', [WorkflowBuilderSessionController::class, 'update'])->name('update');
            Route::delete('{session}', [WorkflowBuilderSessionController::class, 'destroy'])->name('destroy');
            Route::patch('{session}/draft', [WorkflowBuilderSessionController::class, 'syncDraft'])->name('draft');
            Route::post('{session}/promote', [WorkflowBuilderSessionController::class, 'promote'])->name('promote');

            Route::get('{session}/messages', [WorkflowBuilderMessageController::class, 'index'])->name('messages.index');
            Route::post('{session}/messages', [WorkflowBuilderMessageController::class, 'store'])->name('messages.store')->middleware('throttle:workflow-builder-messages');
            Route::get('{session}/messages/{message}', [WorkflowBuilderMessageController::class, 'show'])->name('messages.show');

            // Undo history: see WorkflowBuilderDraftVersionController.
            Route::get('{session}/versions', [WorkflowBuilderDraftVersionController::class, 'index'])->name('versions.index');
            Route::post('{session}/versions/{version}/restore', [WorkflowBuilderDraftVersionController::class, 'restore'])->name('versions.restore');

            // One-shot helpers over the session's draft: see WorkflowBuilderAssistController.
            Route::prefix('{session}/assist')
                ->as('assist.')
                ->middleware('throttle:workflow-builder-assist')
                ->group(function () {
                    Route::post('suggest-nodes', [WorkflowBuilderAssistController::class, 'suggestNodes'])->name('suggest-nodes');
                    Route::post('configure-node', [WorkflowBuilderAssistController::class, 'configureNode'])->name('configure-node');
                    Route::post('explain', [WorkflowBuilderAssistController::class, 'explain'])->name('explain');
                    Route::post('suggest-improvements', [WorkflowBuilderAssistController::class, 'suggestImprovements'])->name('suggest-improvements');
                });
        });
});
