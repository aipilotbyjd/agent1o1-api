<?php

use App\Http\Controllers\Api\Internal\V1\Agents\WorkspaceAgentActionController;
use App\Http\Controllers\Api\Internal\V1\Agents\WorkspaceAgentPolicyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}')
    ->group(function () {
        // The approvals inbox across every agent in the workspace.
        Route::get('agent-actions', [WorkspaceAgentActionController::class, 'index'])->name('agent-actions.index');
        Route::post('agent-actions/decisions', [WorkspaceAgentActionController::class, 'decide'])->name('agent-actions.decide');
        Route::get('agent-actions/{action}', [WorkspaceAgentActionController::class, 'show'])->name('agent-actions.show');

        // Admin guardrails over every agent.
        Route::get('agent-policy', [WorkspaceAgentPolicyController::class, 'show'])->name('agent-policy.show');
        Route::put('agent-policy', [WorkspaceAgentPolicyController::class, 'update'])->name('agent-policy.update');
    });
