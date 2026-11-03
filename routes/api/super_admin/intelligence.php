<?php

use App\Http\Controllers\Api\SuperAdmin\V1\Intelligence\EconomicsController;
use App\Http\Controllers\Api\SuperAdmin\V1\Intelligence\ExperimentController;
use App\Http\Controllers\Api\SuperAdmin\V1\Intelligence\OperationsController;
use App\Http\Controllers\Api\SuperAdmin\V1\Intelligence\RecommendationController;
use App\Http\Controllers\Api\SuperAdmin\V1\Intelligence\SimulationController;
use App\Http\Controllers\Api\SuperAdmin\V1\Intelligence\SpendControlController;
use Illuminate\Support\Facades\Route;

/*
| Admin intelligence: unit economics, live operations, forecasts, risk
| lists, simulators, recommendations, the analyst, spend controls and
| experiments.
|
| Finance data (economics, forecasts, risks, simulations, the analyst) needs
| `revenue.view`; the live board and call explorer need `models.view`;
| spend controls need `system.manage`; applying recommendations and running
| experiments need `pricing.manage`.
*/
$base = [
    'throttle:super-admin', 'auth:super-admin', 'super-admin.ip:account', 'super-admin.active',
    'super-admin.two-factor', 'super-admin.audit',
];

Route::prefix('intelligence')->as('intelligence.')->group(function () use ($base) {
    Route::middleware([...$base, 'super-admin.can:revenue.view'])->group(function () {
        Route::get('economics/overview', [EconomicsController::class, 'overview'])->name('economics.overview');
        Route::get('economics/timeseries', [EconomicsController::class, 'timeseries'])->name('economics.timeseries');
        Route::get('economics/plans', [EconomicsController::class, 'plans'])->name('economics.plans');
        Route::get('economics/models', [EconomicsController::class, 'models'])->name('economics.models');
        Route::get('economics/features', [EconomicsController::class, 'features'])->name('economics.features');
        Route::get('economics/customers', [EconomicsController::class, 'customers'])->name('economics.customers');
        Route::get('economics/customers/{workspace}', [EconomicsController::class, 'customer'])->whereUuid('workspace')->name('economics.customer');
        Route::get('economics/credit-utilization', [EconomicsController::class, 'creditUtilization'])->name('economics.credit-utilization');
        Route::get('forecasts/{kind}', [EconomicsController::class, 'forecast'])->whereIn('kind', ['spend', 'revenue', 'margin', 'credit-run-out', 'accuracy'])->name('forecasts');
        Route::get('risks/{list}', [EconomicsController::class, 'risks'])->whereIn('list', ['margin-at-risk', 'churn', 'upgrade', 'abuse'])->name('risks');
        Route::post('simulate/pricing', [SimulationController::class, 'pricing'])->name('simulate.pricing');
        Route::post('simulate/routing', [SimulationController::class, 'routing'])->name('simulate.routing');
        Route::post('analyst', [SimulationController::class, 'ask'])->middleware('throttle:super-admin-sensitive')->name('analyst');
        Route::get('recommendations', [RecommendationController::class, 'index'])->name('recommendations.index');
        Route::get('experiments', [ExperimentController::class, 'index'])->name('experiments.index');
        Route::get('experiments/{experiment}', [ExperimentController::class, 'results'])->whereUuid('experiment')->name('experiments.results');
    });

    Route::middleware([...$base, 'super-admin.can:models.view'])->group(function () {
        Route::get('live', [OperationsController::class, 'live'])->name('live');
        Route::get('calls', [OperationsController::class, 'calls'])->name('calls');
        Route::get('traces/{traceId}', [OperationsController::class, 'trace'])->where('traceId', '[A-Za-z0-9-]{1,64}')->name('traces.show');
        Route::get('spend-controls', [SpendControlController::class, 'show'])->name('spend-controls.show');
    });

    Route::middleware([...$base, 'super-admin.can:system.manage'])->group(function () {
        Route::put('spend-controls', [SpendControlController::class, 'update'])->middleware('throttle:super-admin-sensitive')->name('spend-controls.update');
        Route::post('workspaces/{workspace}/throttle', [SpendControlController::class, 'throttle'])->whereUuid('workspace')->name('workspaces.throttle');
        Route::delete('workspaces/{workspace}/throttle', [SpendControlController::class, 'unthrottle'])->whereUuid('workspace')->name('workspaces.unthrottle');
    });

    Route::middleware([...$base, 'super-admin.can:pricing.manage'])->group(function () {
        Route::post('recommendations/refresh', [RecommendationController::class, 'refresh'])->middleware('throttle:super-admin-sensitive')->name('recommendations.refresh');
        Route::post('recommendations/{recommendation}/apply', [RecommendationController::class, 'apply'])->whereUuid('recommendation')->name('recommendations.apply');
        Route::post('recommendations/{recommendation}/dismiss', [RecommendationController::class, 'dismiss'])->whereUuid('recommendation')->name('recommendations.dismiss');
        Route::post('experiments', [ExperimentController::class, 'store'])->name('experiments.store');
        Route::post('experiments/{experiment}/start', [ExperimentController::class, 'start'])->whereUuid('experiment')->name('experiments.start');
        Route::post('experiments/{experiment}/stop', [ExperimentController::class, 'stop'])->whereUuid('experiment')->name('experiments.stop');
    });
});
