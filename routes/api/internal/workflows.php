<?php

use App\Http\Controllers\Api\Internal\V1\Workflows\FolderController;
use App\Http\Controllers\Api\Internal\V1\Workflows\PublishApprovalPolicyController;
use App\Http\Controllers\Api\Internal\V1\Workflows\TagController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowFavoriteController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowInterfaceController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowNodePinController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowPublishRequestController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowVersionController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkspacePublishRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/workflows')
    ->as('workflows.')
    ->group(function () {
        Route::get('/', [WorkflowController::class, 'index'])->name('index');
        Route::post('/', [WorkflowController::class, 'store'])->name('store');
        Route::get('trash', [WorkflowController::class, 'trash'])->name('trash');
        Route::get('{workflow}', [WorkflowController::class, 'show'])->name('show');
        Route::patch('{workflow}', [WorkflowController::class, 'update'])->name('update');
        Route::delete('{workflow}', [WorkflowController::class, 'destroy'])->name('destroy');
        Route::post('{workflow}/restore', [WorkflowController::class, 'restore'])->withTrashed()->name('restore');
        Route::delete('{workflow}/force', [WorkflowController::class, 'forceDestroy'])->withTrashed()->name('force-destroy');
        Route::post('{workflow}/duplicate', [WorkflowController::class, 'duplicate'])->name('duplicate');

        Route::get('{workflow}/versions', [WorkflowVersionController::class, 'index'])->name('versions.index');
        Route::post('{workflow}/versions', [WorkflowVersionController::class, 'store'])->name('versions.store');
        Route::get('{workflow}/versions/{version}', [WorkflowVersionController::class, 'show'])->name('versions.show');
        Route::post('{workflow}/versions/{version}/restore', [WorkflowVersionController::class, 'restore'])->name('versions.restore');

        // Review before publishing, in workspaces that require it: see PublishRequests.
        Route::get('{workflow}/publish-requests', [WorkflowPublishRequestController::class, 'index'])->name('publish-requests.index');
        Route::post('{workflow}/publish-requests', [WorkflowPublishRequestController::class, 'store'])->name('publish-requests.store');
        Route::get('{workflow}/publish-requests/{publishRequest}', [WorkflowPublishRequestController::class, 'show'])->name('publish-requests.show');
        Route::post('{workflow}/publish-requests/{publishRequest}/approve', [WorkflowPublishRequestController::class, 'approve'])->name('publish-requests.approve');
        Route::post('{workflow}/publish-requests/{publishRequest}/reject', [WorkflowPublishRequestController::class, 'reject'])->name('publish-requests.reject');
        Route::post('{workflow}/publish-requests/{publishRequest}/withdraw', [WorkflowPublishRequestController::class, 'withdraw'])->name('publish-requests.withdraw');

        // The workflow's input contract and the form-run that submits it:
        // see WorkflowInterfaceController.
        Route::get('{workflow}/interface', [WorkflowInterfaceController::class, 'show'])->name('interface.show');
        Route::put('{workflow}/interface', [WorkflowInterfaceController::class, 'update'])->name('interface.update');
        Route::post('{workflow}/interface/runs', [WorkflowInterfaceController::class, 'submit'])->name('interface.runs.store');

        Route::put('{workflow}/tags', [TagController::class, 'syncForWorkflow'])->name('tags.sync');

        Route::post('{workflow}/nodes/{node}/pin', [WorkflowNodePinController::class, 'store'])->name('nodes.pin');
        Route::delete('{workflow}/nodes/{node}/pin', [WorkflowNodePinController::class, 'destroy'])->name('nodes.unpin');

        Route::patch('{workflow}/favorite', [WorkflowFavoriteController::class, 'update'])->name('favorite.update');
    });

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/folders')
    ->as('folders.')
    ->group(function () {
        Route::get('/', [FolderController::class, 'index'])->name('index');
        Route::post('/', [FolderController::class, 'store'])->name('store');
        Route::post('move-workflows', [FolderController::class, 'moveWorkflows'])->name('move-workflows');
        Route::post('move-agents', [FolderController::class, 'moveAgents'])->name('move-agents');
        Route::patch('{folder}', [FolderController::class, 'update'])->name('update');
        Route::delete('{folder}', [FolderController::class, 'destroy'])->name('destroy');
    });

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/tags')
    ->as('tags.')
    ->group(function () {
        Route::get('/', [TagController::class, 'index'])->name('index');
        Route::post('/', [TagController::class, 'store'])->name('store');
        Route::patch('{tag}', [TagController::class, 'update'])->name('update');
        Route::delete('{tag}', [TagController::class, 'destroy'])->name('destroy');
    });

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}')
    ->group(function () {
        // Whether workflow changes need a review before they go live.
        Route::get('publish-approval-policy', [PublishApprovalPolicyController::class, 'show'])->name('publish-approval-policy.show');
        Route::put('publish-approval-policy', [PublishApprovalPolicyController::class, 'update'])->name('publish-approval-policy.update');

        // Every change waiting for review, for the approvals inbox.
        Route::get('publish-requests', [WorkspacePublishRequestController::class, 'index'])->name('publish-requests.index');
    });
