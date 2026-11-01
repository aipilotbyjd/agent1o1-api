<?php

use App\Http\Controllers\Api\Internal\V1\Workflows\PageSectionController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowPageCommentController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowPageController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowPageGenerationController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowPageSubmissionController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowPageVersionController;
use Illuminate\Support\Facades\Route;

// Saved blocks to reuse across pages.
Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/page-sections')
    ->as('page-sections.')
    ->group(function () {
        Route::get('/', [PageSectionController::class, 'index'])->name('index');
        Route::post('/', [PageSectionController::class, 'store'])->name('store');
        Route::patch('{section}', [PageSectionController::class, 'update'])->name('update');
        Route::delete('{section}', [PageSectionController::class, 'destroy'])->name('destroy');
    });

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/pages')
    ->as('pages.')
    ->group(function () {
        Route::get('/', [WorkflowPageController::class, 'index'])->name('index');
        Route::post('/', [WorkflowPageController::class, 'store'])->name('store');
        Route::get('{page}', [WorkflowPageController::class, 'show'])->name('show');
        Route::patch('{page}', [WorkflowPageController::class, 'update'])->name('update');
        Route::delete('{page}', [WorkflowPageController::class, 'destroy'])->name('destroy');
        Route::post('{page}/duplicate', [WorkflowPageController::class, 'duplicate'])->name('duplicate');
        Route::post('{page}/publish', [WorkflowPageController::class, 'publish'])->name('publish');
        Route::post('{page}/unpublish', [WorkflowPageController::class, 'unpublish'])->name('unpublish');
        Route::post('{page}/rotate-link', [WorkflowPageController::class, 'rotateLink'])->name('rotate-link');

        // The page as a member using it sees it, and the runs started from it.
        Route::post('{page}/variant', [WorkflowPageController::class, 'createVariant'])->name('variant.store');
        Route::delete('{page}/variant', [WorkflowPageController::class, 'removeVariant'])->name('variant.destroy');
        Route::post('{page}/variant/promote', [WorkflowPageController::class, 'promoteVariant'])->name('variant.promote');
        Route::get('{page}/insights', [WorkflowPageController::class, 'insights'])->name('insights');
        Route::get('{page}/submissions/export', [WorkflowPageSubmissionController::class, 'export'])->name('submissions.export');
        Route::post('{page}/events', [WorkflowPageController::class, 'event'])->name('events');

        Route::get('{page}/versions', [WorkflowPageVersionController::class, 'index'])->name('versions.index');
        Route::post('{page}/versions', [WorkflowPageVersionController::class, 'store'])->name('versions.store');
        Route::get('{page}/versions/{version}', [WorkflowPageVersionController::class, 'show'])->name('versions.show');
        Route::post('{page}/versions/{version}/restore', [WorkflowPageVersionController::class, 'restore'])->name('versions.restore');

        Route::get('{page}/comments', [WorkflowPageCommentController::class, 'index'])->name('comments.index');
        Route::post('{page}/comments', [WorkflowPageCommentController::class, 'store'])->name('comments.store');
        Route::patch('{page}/comments/{comment}', [WorkflowPageCommentController::class, 'update'])->name('comments.update');
        Route::delete('{page}/comments/{comment}', [WorkflowPageCommentController::class, 'destroy'])->name('comments.destroy');

        Route::post('{page}/generations', [WorkflowPageGenerationController::class, 'store'])->name('generations.store');
        Route::get('{page}/generations/{generation}', [WorkflowPageGenerationController::class, 'show'])->name('generations.show');

        Route::get('{page}/view', [WorkflowPageController::class, 'view'])->name('view');
        Route::get('{page}/submissions', [WorkflowPageSubmissionController::class, 'index'])->name('submissions.index');
        Route::post('{page}/submissions', [WorkflowPageSubmissionController::class, 'store'])->name('submissions.store');
        Route::get('{page}/submissions/{submission}', [WorkflowPageSubmissionController::class, 'show'])->name('submissions.show');
    });
