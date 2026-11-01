<?php

use App\Http\Controllers\Api\SuperAdmin\V1\Stats\SuperAdminStatsController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'throttle:super-admin', 'auth:super-admin', 'super-admin.ip:account', 'super-admin.active',
    'super-admin.two-factor', 'super-admin.audit', 'super-admin.can:stats.view',
])->prefix('stats')->as('stats.')->group(function () {
    Route::get('overview', [SuperAdminStatsController::class, 'overview'])->name('overview');
    Route::get('timeseries', [SuperAdminStatsController::class, 'timeseries'])->name('timeseries');
    Route::get('top-workspaces', [SuperAdminStatsController::class, 'topWorkspaces'])->name('top-workspaces');
});
