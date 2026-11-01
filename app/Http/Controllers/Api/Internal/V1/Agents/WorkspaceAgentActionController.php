<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Actions\Agents\ResolveAgentActionsAction;
use App\Enums\Agents\ActionEffect;
use App\Enums\Agents\ActionRisk;
use App\Enums\Agents\AgentActionStatus;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\DecideAgentActionsRequest;
use App\Http\Resources\Api\Internal\V1\Agents\AgentActionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\AgentAction;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The workspace's approvals inbox: every agent action waiting on someone,
 * oldest first: it's a work queue, and the agent blocked longest is the one
 * to unblock (same reasoning as `PendingApprovalController`). Filterable to
 * any status for the full action log, and decidable in bulk; decided turns
 * resume on the queue.
 */
class WorkspaceAgentActionController extends Controller
{
    public function __construct(private readonly ResolveAgentActionsAction $resolve) {}

    public function index(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentView);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(AgentActionStatus::class)],
            'agent_id' => ['nullable', 'uuid'],
            'effect' => ['nullable', Rule::enum(ActionEffect::class)],
            'risk' => ['nullable', Rule::enum(ActionRisk::class)],
        ]);

        $status = $filters['status'] ?? AgentActionStatus::Pending->value;

        $actions = AgentAction::query()
            ->with(['agent', 'session'])
            ->where('workspace_id', $workspace->id)
            ->where('status', $status)
            ->when($filters['agent_id'] ?? null, fn (Builder $query, string $agentId) => $query->where('agent_id', $agentId))
            ->when($filters['effect'] ?? null, fn (Builder $query, string $effect) => $query->where('effect', $effect))
            ->when($filters['risk'] ?? null, fn (Builder $query, string $risk) => $query->where('risk', $risk))
            ->when(
                $status === AgentActionStatus::Pending->value,
                fn (Builder $query) => $query->oldest('requested_at'),
                fn (Builder $query) => $query->latest('id'),
            )
            ->paginate(25);

        return ApiResponse::paginated(AgentActionResource::collection($actions));
    }

    public function show(Workspace $workspace, AgentAction $action)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $action);

        return ApiResponse::success(['action' => AgentActionResource::make($action->load(['agent', 'session']))]);
    }

    public function decide(DecideAgentActionsRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentChat);

        $decisions = $request->decisions();

        $foreign = AgentAction::query()
            ->whereKey(array_column($decisions, 'action_id'))
            ->where('workspace_id', '!=', $workspace->id)
            ->exists();

        abort_if($foreign, 404);

        $decided = $this->resolve->execute($request->user(), $decisions);

        return ApiResponse::success(['actions' => AgentActionResource::collection($decided)], 'Decisions recorded.');
    }
}
