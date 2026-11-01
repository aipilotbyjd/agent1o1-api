<?php

use App\Http\Controllers\Api\SuperAdmin\V1\Models\SuperAdminEndpointController;
use App\Http\Controllers\Api\SuperAdmin\V1\Models\SuperAdminModelController;
use App\Http\Controllers\Api\SuperAdmin\V1\Models\SuperAdminModelInsightController;
use App\Http\Controllers\Api\SuperAdmin\V1\Models\SuperAdminModelLifecycleController;
use App\Http\Controllers\Api\SuperAdmin\V1\Models\SuperAdminModelPriceController;
use App\Http\Controllers\Api\SuperAdmin\V1\Models\SuperAdminModelRouteController;
use App\Http\Controllers\Api\SuperAdmin\V1\Models\SuperAdminProviderController;
use Illuminate\Support\Facades\Route;

/*
| Managing the AI models: the catalog and its routes, what each backend model
| costs, the operator's own endpoints, retirement, and the read-only views
| (health, providers, workspaces' own keys, usage, margins).
|
| Reading needs `models.view` (every role); changing the catalog, routes and
| endpoints needs `models.manage`; prices need `models.prices`. Changes to an
| internal model, and anything that stores a secret, also need a fresh
| authenticator code (see the form requests).
*/
$base = [
    'throttle:super-admin', 'auth:super-admin', 'super-admin.ip:account', 'super-admin.active',
    'super-admin.two-factor', 'super-admin.audit',
];

Route::prefix('models')->as('models.')->group(function () use ($base) {
    Route::middleware([...$base, 'super-admin.can:models.view'])->group(function () {
        Route::get('/', [SuperAdminModelController::class, 'index'])->name('index');
        Route::get('health', [SuperAdminModelInsightController::class, 'health'])->name('health');
        Route::get('providers', [SuperAdminModelInsightController::class, 'providers'])->name('providers');
        Route::get('byok', [SuperAdminModelInsightController::class, 'byok'])->name('byok');
        Route::get('usage', [SuperAdminModelInsightController::class, 'usage'])->name('usage');
        Route::get('usage/workspaces', [SuperAdminModelInsightController::class, 'topSpenders'])->name('usage.workspaces');
        Route::get('usage/daily', [SuperAdminModelInsightController::class, 'dailyUsage'])->name('usage.daily');
        Route::get('margins', [SuperAdminModelInsightController::class, 'margins'])->name('margins');
        Route::get('prices', [SuperAdminModelPriceController::class, 'index'])->name('prices.index');
        Route::get('{model}', [SuperAdminModelController::class, 'show'])->whereUuid('model')->name('show');
        Route::get('{model}/retirement-impact', [SuperAdminModelLifecycleController::class, 'impact'])->whereUuid('model')->name('impact');
    });

    Route::middleware([...$base, 'super-admin.can:models.manage'])->group(function () {
        Route::get('endpoints', [SuperAdminEndpointController::class, 'index'])->name('endpoints.index');
        Route::post('endpoints', [SuperAdminEndpointController::class, 'store'])->middleware('throttle:super-admin-sensitive')->name('endpoints.store');
        Route::patch('endpoints/{endpoint}', [SuperAdminEndpointController::class, 'update'])->middleware('throttle:super-admin-sensitive')->whereUuid('endpoint')->name('endpoints.update');
        Route::delete('endpoints/{endpoint}', [SuperAdminEndpointController::class, 'destroy'])->middleware('throttle:super-admin-sensitive')->whereUuid('endpoint')->name('endpoints.destroy');
        Route::post('endpoints/{endpoint}/test', [SuperAdminEndpointController::class, 'test'])->middleware('throttle:super-admin-sensitive')->whereUuid('endpoint')->name('endpoints.test');

        Route::post('providers/{provider}/pause', [SuperAdminProviderController::class, 'pause'])->middleware('throttle:super-admin-sensitive')->where('provider', '[A-Za-z0-9._-]+')->name('providers.pause');
        Route::post('providers/{provider}/resume', [SuperAdminProviderController::class, 'resume'])->middleware('throttle:super-admin-sensitive')->where('provider', '[A-Za-z0-9._-]+')->name('providers.resume');

        Route::post('/', [SuperAdminModelController::class, 'store'])->name('store');
        Route::patch('{model}', [SuperAdminModelController::class, 'update'])->whereUuid('model')->name('update');

        Route::post('{model}/routes', [SuperAdminModelRouteController::class, 'store'])->whereUuid('model')->name('routes.store');
        Route::post('{model}/routes/reorder', [SuperAdminModelRouteController::class, 'reorder'])->whereUuid('model')->name('routes.reorder');
        Route::patch('{model}/routes/{route}', [SuperAdminModelRouteController::class, 'update'])->whereUuid(['model', 'route'])->name('routes.update');
        Route::delete('{model}/routes/{route}', [SuperAdminModelRouteController::class, 'destroy'])->whereUuid(['model', 'route'])->name('routes.destroy');
        Route::post('{model}/routes/{route}/test', [SuperAdminModelRouteController::class, 'test'])
            ->middleware('throttle:super-admin-sensitive')
            ->whereUuid(['model', 'route'])
            ->name('routes.test');

        Route::post('{model}/deprecate', [SuperAdminModelLifecycleController::class, 'deprecate'])->whereUuid('model')->name('deprecate');
        Route::post('{model}/retire', [SuperAdminModelLifecycleController::class, 'retire'])->whereUuid('model')->name('retire');
        Route::post('{model}/restore', [SuperAdminModelLifecycleController::class, 'restore'])->whereUuid('model')->name('restore');
    });

    Route::middleware([...$base, 'super-admin.can:models.prices'])->group(function () {
        Route::put('prices', [SuperAdminModelPriceController::class, 'upsert'])->name('prices.upsert');
        Route::delete('prices/{price}', [SuperAdminModelPriceController::class, 'destroy'])->whereUuid('price')->name('prices.destroy');
    });
});
