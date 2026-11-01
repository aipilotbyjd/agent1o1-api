<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Agents\AgentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Workspaces\Workspace;

class AgentSubagentController extends Controller
{
    public function index(Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        return ApiResponse::success(['subagents' => AgentResource::collection($agent->subagents)]);
    }

    public function store(Workspace $workspace, Agent $agent, Agent $subagent)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        $this->ensureBelongsToWorkspace($workspace, $subagent);

        if ($subagent->is($agent)) {
            return ApiResponse::error('An agent cannot be its own subagent; use self-cloning instead.', 422);
        }

        $agent->subagents()->syncWithoutDetaching([$subagent->id]);

        return ApiResponse::success(['subagents' => AgentResource::collection($agent->subagents()->get())], 'Subagent added.');
    }

    public function destroy(Workspace $workspace, Agent $agent, Agent $subagent)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $agent->subagents()->detach($subagent->id);

        return ApiResponse::noContent();
    }
}
