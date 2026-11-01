<?php

namespace App\Http\Controllers\Api\Public\V1\Runs;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Public\V1\NodeRunResource;
use App\Http\Responses\ApiResponse;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use Illuminate\Http\Request;

/**
 * Step-level results for a run: what an integrator polls to find out which
 * step failed, rather than inferring it from the run's error string.
 */
class NodeRunController extends Controller
{
    public function index(Request $request, Run $run)
    {
        $this->ensureBelongsToWorkspace($this->apiKeyWorkspace($request), $run);

        return ApiResponse::success([
            'node_runs' => NodeRunResource::collection($run->nodeRuns()->with('creditTransaction')->oldest('id')->get()),
        ]);
    }

    public function show(Request $request, Run $run, NodeRun $nodeRun)
    {
        $this->ensureBelongsToWorkspace($this->apiKeyWorkspace($request), $run);
        abort_if($nodeRun->run_id !== $run->id, 404);

        return ApiResponse::success(['node_run' => NodeRunResource::make($nodeRun->load('creditTransaction'))]);
    }
}
