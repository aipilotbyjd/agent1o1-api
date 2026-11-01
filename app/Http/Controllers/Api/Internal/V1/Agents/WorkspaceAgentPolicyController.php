<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\UpdateWorkspaceAgentPolicyRequest;
use App\Http\Resources\Api\Internal\V1\Agents\WorkspaceAgentPolicyResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\WorkspaceAgentPolicy;
use App\Models\Workspaces\Workspace;

/**
 * The workspace's guardrails over every agent: readable by anyone who can
 * see agents (so they know why an action was blocked), changeable only by
 * an admin.
 */
class WorkspaceAgentPolicyController extends Controller
{
    public function show(Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentView);

        return ApiResponse::success(['policy' => WorkspaceAgentPolicyResource::make(WorkspaceAgentPolicy::forWorkspace($workspace->id))]);
    }

    public function update(UpdateWorkspaceAgentPolicyRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentPolicyManage);

        $policy = WorkspaceAgentPolicy::query()->updateOrCreate(['workspace_id' => $workspace->id], $request->validated());

        return ApiResponse::success(['policy' => WorkspaceAgentPolicyResource::make($policy)], 'Agent policy updated.');
    }
}
