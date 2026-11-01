<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\StoreAgentEvalCaseRequest;
use App\Http\Requests\Api\Internal\V1\Agents\UpdateAgentEvalCaseRequest;
use App\Http\Resources\Api\Internal\V1\Agents\AgentEvalCaseResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentEvalCase;
use App\Models\Agents\AgentEvalSuite;
use App\Models\Workspaces\Workspace;

class AgentEvalCaseController extends Controller
{
    public function index(Workspace $workspace, Agent $agent, AgentEvalSuite $suite)
    {
        $this->requirePermission(Permission::AgentView);
        $this->authorizeSuite($workspace, $agent, $suite);

        return ApiResponse::success([
            'cases' => AgentEvalCaseResource::collection($suite->cases),
        ]);
    }

    public function store(StoreAgentEvalCaseRequest $request, Workspace $workspace, Agent $agent, AgentEvalSuite $suite)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->authorizeSuite($workspace, $agent, $suite);

        $case = $suite->cases()->create($request->validated());

        return ApiResponse::created(['case' => AgentEvalCaseResource::make($case)], 'Eval case created successfully.');
    }

    public function update(UpdateAgentEvalCaseRequest $request, Workspace $workspace, Agent $agent, AgentEvalSuite $suite, AgentEvalCase $case)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->authorizeSuite($workspace, $agent, $suite);
        abort_if($case->agent_eval_suite_id !== $suite->id, 404);

        $case->update($request->validated());

        return ApiResponse::success(['case' => AgentEvalCaseResource::make($case)], 'Eval case updated successfully.');
    }

    public function destroy(Workspace $workspace, Agent $agent, AgentEvalSuite $suite, AgentEvalCase $case)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->authorizeSuite($workspace, $agent, $suite);
        abort_if($case->agent_eval_suite_id !== $suite->id, 404);

        $case->delete();

        return ApiResponse::noContent();
    }

    private function authorizeSuite(Workspace $workspace, Agent $agent, AgentEvalSuite $suite): void
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($suite->agent_id !== $agent->id, 404);
    }
}
