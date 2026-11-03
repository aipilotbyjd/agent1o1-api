<?php

namespace App\Http\Controllers\Api\Internal\V1\Runs;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Runs\Run;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\RunCostBreakdown;

/**
 * What a run or chat turn cost, line by line, for the live cost badge and the
 * cost details panel. Polled while the run is in progress.
 */
class RunCostController extends Controller
{
    public function __invoke(Workspace $workspace, Run $run, RunCostBreakdown $breakdown)
    {
        $this->requirePermission(Permission::RunView);
        $this->ensureBelongsToWorkspace($workspace, $run);

        return ApiResponse::success($breakdown->for($run));
    }
}
