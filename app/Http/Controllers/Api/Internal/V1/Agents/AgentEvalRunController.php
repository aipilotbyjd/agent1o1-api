<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Agents\AgentEvalRunResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentEvalRun;
use App\Models\Agents\AgentEvalSuite;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\EvalRunner;
use Illuminate\Http\Request;

/**
 * Executing a suite needs `AgentManage` *and* `AgentChat`: it edits nothing,
 * but it really does run the agent (tools included) so trigger rights are
 * as necessary here as they are for chatting with it.
 *
 * `store()` queues the suite and returns the run as `pending`; clients poll
 * `show()` for the graded result.
 */
class AgentEvalRunController extends Controller
{
    public function __construct(private readonly EvalRunner $runner) {}

    public function index(Workspace $workspace, Agent $agent, AgentEvalSuite $suite)
    {
        $this->requirePermission(Permission::AgentView);
        $this->authorizeSuite($workspace, $agent, $suite);

        return ApiResponse::success([
            'runs' => AgentEvalRunResource::collection($suite->runs()->latest()->get()),
        ]);
    }

    public function store(Request $request, Workspace $workspace, Agent $agent, AgentEvalSuite $suite)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->requirePermission(Permission::AgentChat);
        $this->authorizeSuite($workspace, $agent, $suite);

        $evalRun = $this->runner->start($suite, $request->user());

        return ApiResponse::created([
            'run' => AgentEvalRunResource::make($evalRun->load('results.evalCase')),
        ], 'Eval suite queued.');
    }

    public function show(Workspace $workspace, Agent $agent, AgentEvalSuite $suite, AgentEvalRun $evalRun)
    {
        $this->requirePermission(Permission::AgentView);
        $this->authorizeSuite($workspace, $agent, $suite);
        abort_if($evalRun->agent_eval_suite_id !== $suite->id, 404);

        return ApiResponse::success([
            'run' => AgentEvalRunResource::make($evalRun->load('results.evalCase')),
        ]);
    }

    private function authorizeSuite(Workspace $workspace, Agent $agent, AgentEvalSuite $suite): void
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($suite->agent_id !== $agent->id, 404);
    }
}
