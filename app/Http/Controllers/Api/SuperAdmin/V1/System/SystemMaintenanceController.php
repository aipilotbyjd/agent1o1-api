<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use App\Http\Responses\ApiResponse;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use App\Services\SuperAdmin\System\HorizonInspector;
use Illuminate\Support\Facades\Artisan;

/**
 * The few operational levers: every one needs a reason and is audited.
 */
class SystemMaintenanceController extends Controller
{
    public function __construct(
        private readonly HorizonInspector $horizon,
        private readonly SuperAdminAuditLogger $audit,
    ) {}

    public function pauseHorizon(ReasonRequest $request)
    {
        $this->horizon->pause();
        $this->audit->record($request->user('super-admin'), 'system.horizon_paused', null, null, null, $request->validated('reason'));

        return ApiResponse::success(null, 'Horizon was told to pause.');
    }

    public function continueHorizon(ReasonRequest $request)
    {
        $this->horizon->resume();
        $this->audit->record($request->user('super-admin'), 'system.horizon_continued', null, null, null, $request->validated('reason'));

        return ApiResponse::success(null, 'Horizon was told to continue.');
    }

    /**
     * Workers finish their current job and restart, picking up new code and config.
     */
    public function restartWorkers(ReasonRequest $request)
    {
        Artisan::call('queue:restart');
        $this->audit->record($request->user('super-admin'), 'system.workers_restarted', null, null, null, $request->validated('reason'));

        return ApiResponse::success(null, 'Queue workers will restart after their current job.');
    }
}
