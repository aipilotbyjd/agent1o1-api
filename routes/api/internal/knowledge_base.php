<?php

use App\Http\Controllers\Api\Internal\V1\KnowledgeBase\KnowledgeBaseController;
use App\Http\Controllers\Api\Internal\V1\KnowledgeBase\KnowledgeSourceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/knowledge-base')
    ->as('knowledge-base.')
    ->group(function () {
        Route::get('/', [KnowledgeBaseController::class, 'index'])->name('index');
        Route::post('/', [KnowledgeBaseController::class, 'store'])->name('store');
        Route::post('search', [KnowledgeBaseController::class, 'search'])->middleware('throttle:30,1')->name('search');
        Route::get('document', [KnowledgeBaseController::class, 'document'])->name('document');
        Route::delete('document', [KnowledgeBaseController::class, 'destroyDocument'])->name('document.destroy');

        Route::get('collections', [KnowledgeBaseController::class, 'collections'])->name('collections.index');
        Route::delete('collections/{collection}', [KnowledgeBaseController::class, 'destroyCollection'])->name('collections.destroy');

        Route::get('sources', [KnowledgeSourceController::class, 'index'])->name('sources.index');
        Route::get('sources/apps', [KnowledgeSourceController::class, 'apps'])->name('sources.apps');
        Route::get('sources/options', [KnowledgeSourceController::class, 'options'])->middleware('throttle:60,1')->name('sources.options');
        Route::post('sources', [KnowledgeSourceController::class, 'store'])->middleware('throttle:30,1')->name('sources.store');
        Route::patch('sources/{knowledgeSource}', [KnowledgeSourceController::class, 'update'])->name('sources.update');
        Route::delete('sources/{knowledgeSource}', [KnowledgeSourceController::class, 'destroy'])->name('sources.destroy');
        Route::get('sources/{knowledgeSource}/documents', [KnowledgeSourceController::class, 'documents'])->name('sources.documents');
        Route::post('sources/{knowledgeSource}/sync', [KnowledgeSourceController::class, 'sync'])->middleware('throttle:10,1')->name('sources.sync');

        Route::delete('{documentEmbedding}', [KnowledgeBaseController::class, 'destroy'])->name('destroy');
    });
