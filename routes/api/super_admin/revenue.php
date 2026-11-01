<?php

use App\Http\Controllers\Api\SuperAdmin\V1\Revenue\GrowthController;
use App\Http\Controllers\Api\SuperAdmin\V1\Revenue\RevenueController;
use Illuminate\Support\Facades\Route;

$base = [
    'throttle:super-admin', 'auth:super-admin', 'super-admin.ip:account', 'super-admin.active',
    'super-admin.two-factor', 'super-admin.audit',
];

// Revenue is finance data, so it has its own ability instead of riding on stats.view.
Route::middleware([...$base, 'super-admin.can:revenue.view'])->group(function () {
    Route::prefix('revenue')->as('revenue.')->group(function () {
        Route::get('overview', [RevenueController::class, 'overview'])->name('overview');
        Route::get('mrr-movement', [RevenueController::class, 'mrrMovement'])->name('mrr-movement');
        Route::get('timeseries', [RevenueController::class, 'timeseries'])->name('timeseries');
        Route::get('plans', [RevenueController::class, 'plans'])->name('plans');
        Route::get('churn', [RevenueController::class, 'churn'])->name('churn');
        Route::get('trials', [RevenueController::class, 'trials'])->name('trials');
        Route::get('failed-payments', [RevenueController::class, 'failedPayments'])->name('failed-payments');
        Route::get('cohorts', [RevenueController::class, 'cohorts'])->name('cohorts');
        Route::get('ltv', [RevenueController::class, 'ltv'])->name('ltv');
        Route::get('credits', [RevenueController::class, 'credits'])->name('credits');
        Route::get('top-customers', [RevenueController::class, 'topCustomers'])->name('top-customers');
        Route::get('export', [RevenueController::class, 'export'])->middleware('throttle:super-admin-sensitive')->name('export');
    });

    Route::prefix('growth')->as('growth.')->group(function () {
        Route::get('funnel', [GrowthController::class, 'funnel'])->name('funnel');
        Route::get('activation', [GrowthController::class, 'activation'])->name('activation');
        Route::get('retention', [GrowthController::class, 'retention'])->name('retention');
        Route::get('acquisition', [GrowthController::class, 'acquisition'])->name('acquisition');
        Route::get('active-users', [GrowthController::class, 'activeUsers'])->name('active-users');
        Route::get('at-risk', [GrowthController::class, 'atRisk'])->name('at-risk');
        Route::get('expansion', [GrowthController::class, 'expansion'])->name('expansion');
    });
});
