<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\StoreAgentEvalSuiteRequest;
use App\Http\Requests\Api\Internal\V1\Agents\UpdateAgentEvalSuiteRequest;
use App\Http\Resources\Api\Internal\V1\Agents\AgentEvalSuiteResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentEvalSuite;
use App\Models\Workspaces\Workspace;

class AgentEvalSuiteController extends Controller
{
    public function index(Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        return ApiResponse::success([
            'suites' => AgentEvalSuiteResource::collection(
                $agent->evalSuites()->withCount('cases')->latest()->get(),
            ),
        ]);
    }

    public function store(StoreAgentEvalSuiteRequest $request, Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $suite = $agent->evalSuites()->create([
            ...$request->validated(),
            'workspace_id' => $agent->workspace_id,
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(['suite' => AgentEvalSuiteResource::make($suite)], 'Eval suite created successfully.');
    }

    public function show(Workspace $workspace, Agent $agent, AgentEvalSuite $suite)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($suite->agent_id !== $agent->id, 404);

        return ApiResponse::success([
            'suite' => AgentEvalSuiteResource::make($suite->load('cases')),
        ]);
    }

    public function update(UpdateAgentEvalSuiteRequest $request, Workspace $workspace, Agent $agent, AgentEvalSuite $suite)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($suite->agent_id !== $agent->id, 404);

        $suite->update($request->validated());

        return ApiResponse::success(['suite' => AgentEvalSuiteResource::make($suite)], 'Eval suite updated successfully.');
    }

    public function destroy(Workspace $workspace, Agent $agent, AgentEvalSuite $suite)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($suite->agent_id !== $agent->id, 404);

        $suite->delete();

        return ApiResponse::noContent();
    }
}
