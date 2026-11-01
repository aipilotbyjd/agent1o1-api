<?php

use App\Http\Controllers\Api\Internal\V1\Dashboard\CreditUsageController;
use App\Http\Controllers\Api\Internal\V1\Dashboard\DashboardController;
use App\Http\Controllers\Api\Internal\V1\Dashboard\PendingApprovalController;
use App\Http\Controllers\Api\Internal\V1\Dashboard\RunStatsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/dashboard')
    ->as('dashboard.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'overview'])->name('overview');
        Route::get('run-stats', [RunStatsController::class, 'index'])->name('run-stats.index');
        Route::get('credit-usage', [CreditUsageController::class, 'index'])->name('credit-usage.index');
        Route::get('pending-approvals', [PendingApprovalController::class, 'index'])->name('pending-approvals.index');
    });
