<?php

namespace App\Http\Controllers\Api\Internal\V1\Runs;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Runs\NodeRunDetailResource;
use App\Http\Responses\ApiResponse;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\Workspaces\Workspace;

/**
 * Per-step drill-down for a run. `RunController::show()` returns every node
 * run as a summary (`NodeRunResource`); this is the one step in full, which
 * is what a run canvas opens when a node is clicked.
 */
class NodeRunController extends Controller
{
    public function index(Workspace $workspace, Run $run)
    {
        $this->requirePermission(Permission::RunView);
        $this->ensureBelongsToWorkspace($workspace, $run);

        return ApiResponse::success([
            'node_runs' => NodeRunDetailResource::collection(
                $run->nodeRuns()->with('creditTransaction')->oldest('id')->get(),
            ),
        ]);
    }

    public function show(Workspace $workspace, Run $run, NodeRun $nodeRun)
    {
        $this->requirePermission(Permission::RunView);
        $this->ensureBelongsToWorkspace($workspace, $run);
        abort_if($nodeRun->run_id !== $run->id, 404);

        return ApiResponse::success([
            'node_run' => NodeRunDetailResource::make($nodeRun->load(['childRuns', 'creditTransaction'])),
        ]);
    }
}
