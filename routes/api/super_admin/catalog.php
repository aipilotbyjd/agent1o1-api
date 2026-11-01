<?php

use App\Http\Controllers\Api\SuperAdmin\V1\Catalog\SuperAdminAppController;
use App\Http\Controllers\Api\SuperAdmin\V1\Catalog\SuperAdminCatalogController;
use App\Http\Controllers\Api\SuperAdmin\V1\Catalog\SuperAdminCatalogHealthController;
use App\Http\Controllers\Api\SuperAdmin\V1\Catalog\SuperAdminTaxonomyController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'throttle:super-admin', 'auth:super-admin', 'super-admin.ip:account', 'super-admin.active',
    'super-admin.two-factor', 'super-admin.audit', 'super-admin.can:catalog.manage',
])->prefix('catalog')->as('catalog.')->group(function () {
    Route::get('connectors', [SuperAdminCatalogController::class, 'connectors'])->name('connectors.index');
    Route::post('connectors/{connector}/enable', [SuperAdminCatalogController::class, 'enableConnector'])->whereUuid('connector')->name('connectors.enable');
    Route::post('connectors/{connector}/disable', [SuperAdminCatalogController::class, 'disableConnector'])->whereUuid('connector')->name('connectors.disable');

    Route::get('nodes', [SuperAdminCatalogController::class, 'nodes'])->name('nodes.index');
    Route::post('nodes/{node}/enable', [SuperAdminCatalogController::class, 'enableNode'])->whereUuid('node')->name('nodes.enable');
    Route::post('nodes/{node}/disable', [SuperAdminCatalogController::class, 'disableNode'])->whereUuid('node')->name('nodes.disable');

    Route::get('apps', [SuperAdminAppController::class, 'index'])->name('apps.index');
    Route::post('apps/bulk', [SuperAdminAppController::class, 'bulk'])->name('apps.bulk');
    Route::get('apps/{category}', [SuperAdminAppController::class, 'show'])->whereUuid('category')->name('apps.show');
    Route::put('apps/{category}', [SuperAdminAppController::class, 'update'])->whereUuid('category')->name('apps.update');
    Route::get('apps/{category}/impact', [SuperAdminAppController::class, 'impact'])->whereUuid('category')->name('apps.impact');
    Route::get('apps/{category}/history', [SuperAdminAppController::class, 'history'])->whereUuid('category')->name('apps.history');
    Route::post('apps/{category}/move', [SuperAdminAppController::class, 'move'])->whereUuid('category')->name('apps.move');
    Route::post('apps/{category}/revert/{log}', [SuperAdminAppController::class, 'revert'])->whereUuid(['category', 'log'])->name('apps.revert');
    Route::get('apps/{category}/nodes', [SuperAdminAppController::class, 'nodes'])->whereUuid('category')->name('apps.nodes');
    Route::put('apps/{category}/nodes/{type}', [SuperAdminAppController::class, 'setNodeStatus'])->whereUuid('category')->where('type', '[A-Za-z0-9_]+')->name('apps.nodes.status');

    Route::get('health', SuperAdminCatalogHealthController::class)->name('health');

    Route::get('taxonomy', [SuperAdminTaxonomyController::class, 'index'])->name('taxonomy.index');
    Route::post('taxonomy/{kind}', [SuperAdminTaxonomyController::class, 'store'])->whereIn('kind', ['functions', 'vendors'])->name('taxonomy.store');
    Route::put('taxonomy/{kind}/{id}', [SuperAdminTaxonomyController::class, 'update'])->whereIn('kind', ['functions', 'vendors'])->whereUuid('id')->name('taxonomy.update');
    Route::delete('taxonomy/{kind}/{id}', [SuperAdminTaxonomyController::class, 'destroy'])->whereIn('kind', ['functions', 'vendors'])->whereUuid('id')->name('taxonomy.destroy');

    Route::get('app-categories', [SuperAdminCatalogController::class, 'appCategories'])->name('app-categories.index');
    Route::put('app-categories/{category}', [SuperAdminCatalogController::class, 'updateAppCategory'])->whereUuid('category')->name('app-categories.update');

    Route::get('templates/{type}', [SuperAdminCatalogController::class, 'templates'])->name('templates.index');
    Route::post('templates/{type}/bulk', [SuperAdminCatalogController::class, 'bulkTemplates'])->name('templates.bulk');
    Route::put('templates/{type}/{template}/curation', [SuperAdminCatalogController::class, 'curateTemplate'])->whereUuid('template')->name('templates.curate');
    Route::post('templates/{type}/{template}/publish', [SuperAdminCatalogController::class, 'publishTemplate'])->whereUuid('template')->name('templates.publish');
    Route::post('templates/{type}/{template}/unpublish', [SuperAdminCatalogController::class, 'unpublishTemplate'])->whereUuid('template')->name('templates.unpublish');
});
