<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\StoreAgentMemoryRequest;
use App\Http\Requests\Api\Internal\V1\Agents\UpdateAgentMemoryRequest;
use App\Http\Resources\Api\Internal\V1\Agents\AgentMemoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentMemory;
use App\Models\Workspaces\Workspace;

class AgentMemoryController extends Controller
{
    public function index(Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        return ApiResponse::success([
            'memories' => AgentMemoryResource::collection($agent->memories()->latest()->get()),
        ]);
    }

    public function store(StoreAgentMemoryRequest $request, Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $memory = $agent->memories()->create($request->validated());

        return ApiResponse::created(['memory' => AgentMemoryResource::make($memory)], 'Memory created.');
    }

    public function update(UpdateAgentMemoryRequest $request, Workspace $workspace, Agent $agent, AgentMemory $memory)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($memory->agent_id !== $agent->id, 404);

        $memory->update($request->validated());

        return ApiResponse::success(['memory' => AgentMemoryResource::make($memory)], 'Memory updated.');
    }

    public function destroy(Workspace $workspace, Agent $agent, AgentMemory $memory)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($memory->agent_id !== $agent->id, 404);

        $memory->delete();

        return ApiResponse::noContent();
    }
}
