<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\StoreAgentToolBindingRequest;
use App\Http\Requests\Api\Internal\V1\Agents\UpdateAgentToolBindingRequest;
use App\Http\Resources\Api\Internal\V1\Agents\AgentToolBindingResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentToolBinding;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\NodeRegistry;

class AgentToolBindingController extends Controller
{
    public function __construct(private readonly NodeRegistry $nodes) {}

    public function index(Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        return ApiResponse::success([
            'tool_bindings' => AgentToolBindingResource::collection($agent->toolBindings),
        ]);
    }

    public function store(StoreAgentToolBindingRequest $request, Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        if (! $this->nodes->has($request->validated('node_type'))) {
            return ApiResponse::error("No node is registered for type [{$request->validated('node_type')}].", 422);
        }

        $binding = $agent->toolBindings()->create($request->validated());

        return ApiResponse::created(['tool_binding' => AgentToolBindingResource::make($binding)], 'Tool attached successfully.');
    }

    /**
     * Changes a tool's bound config or its approval rule: see
     * `Services\Agents\Approvals\ActionGate` for how the rule is applied.
     */
    public function update(UpdateAgentToolBindingRequest $request, Workspace $workspace, Agent $agent, AgentToolBinding $toolBinding)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($toolBinding->agent_id !== $agent->id, 404);

        $toolBinding->update($request->validated());

        return ApiResponse::success(['tool_binding' => AgentToolBindingResource::make($toolBinding)], 'Tool updated successfully.');
    }

    public function destroy(Workspace $workspace, Agent $agent, AgentToolBinding $toolBinding)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($toolBinding->agent_id !== $agent->id, 404);

        $toolBinding->delete();

        return ApiResponse::noContent();
    }
}
