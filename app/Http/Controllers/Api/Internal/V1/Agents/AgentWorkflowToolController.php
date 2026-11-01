<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\UpdateAgentWorkflowToolRequest;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;

class AgentWorkflowToolController extends Controller
{
    public function index(Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        return ApiResponse::success([
            'workflows' => $this->withPolicies($agent),
        ]);
    }

    public function store(Workspace $workspace, Agent $agent, Workflow $workflow)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($workflow->workspace_id !== $workspace->id, 404);

        $agent->workflows()->syncWithoutDetaching([$workflow->id]);

        return ApiResponse::success(['workflows' => $this->withPolicies($agent)], 'Workflow attached successfully.');
    }

    /**
     * Sets the approval rule for running this workflow as a tool: the same
     * shape a node tool's `approval_policy` takes.
     */
    public function update(UpdateAgentWorkflowToolRequest $request, Workspace $workspace, Agent $agent, Workflow $workflow)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_unless($agent->workflows()->whereKey($workflow->id)->exists(), 404);

        $policy = $request->validated('approval_policy');

        $agent->workflows()->updateExistingPivot($workflow->id, [
            'approval_policy' => $policy === null ? null : json_encode($policy),
        ]);

        return ApiResponse::success(['workflows' => $this->withPolicies($agent)], 'Workflow tool updated successfully.');
    }

    public function destroy(Workspace $workspace, Agent $agent, Workflow $workflow)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $agent->workflows()->detach($workflow->id);

        return ApiResponse::noContent();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function withPolicies(Agent $agent): array
    {
        return $agent->workflows()->get()
            ->map(function (Workflow $workflow): array {
                $policy = $workflow->pivot->approval_policy;

                return [
                    ...WorkflowResource::make($workflow)->resolve(),
                    'approval_policy' => is_string($policy) ? json_decode($policy, true) : $policy,
                ];
            })
            ->all();
    }
}
