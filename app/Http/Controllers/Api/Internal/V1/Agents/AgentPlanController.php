<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Agents\AgentPlanStatus;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\ApproveAgentPlanRequest;
use App\Http\Requests\Api\Internal\V1\Agents\RejectAgentPlanRequest;
use App\Http\Resources\Api\Internal\V1\Agents\AgentPlanResource;
use App\Http\Responses\ApiResponse;
use App\Jobs\Agents\ExecuteApprovedPlanJob;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentPlan;
use App\Models\Agents\AgentSession;
use App\Models\Workspaces\Workspace;

/**
 * Plan mode's review step: the plans an agent proposed in a conversation,
 * and approving or rejecting one. Deciding needs the same right as deciding
 * the actions themselves: the person the conversation belongs to, or a
 * role that may approve agent actions.
 */
class AgentPlanController extends Controller
{
    public function index(Workspace $workspace, Agent $agent, AgentSession $session)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($session->agent_id !== $agent->id, 404);

        return ApiResponse::success(['plans' => AgentPlanResource::collection($session->plans()->latest('id')->get())]);
    }

    public function approve(ApproveAgentPlanRequest $request, Workspace $workspace, Agent $agent, AgentSession $session, AgentPlan $plan)
    {
        $this->authorizePlan($workspace, $agent, $session, $plan);

        $skipped = $request->validated('skip_step_ids') ?? [];

        $plan->forceFill([
            'status' => AgentPlanStatus::Approved,
            'steps' => array_map(
                fn (array $step): array => in_array($step['id'], $skipped, true) ? [...$step, 'status' => AgentPlan::STEP_SKIPPED] : $step,
                $plan->steps,
            ),
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
            'decision_note' => $request->validated('note'),
        ])->save();

        if ($plan->pendingSteps() === []) {
            $plan->forceFill(['status' => AgentPlanStatus::Completed])->save();
        } elseif ($request->boolean('execute')) {
            ExecuteApprovedPlanJob::dispatch($plan->id);
        }

        return ApiResponse::success(['plan' => AgentPlanResource::make($plan)], 'Plan approved.');
    }

    public function reject(RejectAgentPlanRequest $request, Workspace $workspace, Agent $agent, AgentSession $session, AgentPlan $plan)
    {
        $this->authorizePlan($workspace, $agent, $session, $plan);

        $plan->forceFill([
            'status' => AgentPlanStatus::Rejected,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
            'decision_note' => $request->validated('note'),
        ])->save();

        return ApiResponse::success(['plan' => AgentPlanResource::make($plan)], 'Plan rejected.');
    }

    private function authorizePlan(Workspace $workspace, Agent $agent, AgentSession $session, AgentPlan $plan): void
    {
        $this->requirePermission(Permission::AgentChat);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($session->agent_id !== $agent->id || $plan->agent_session_id !== $session->id, 404);

        if ($session->user_id !== request()->user()->id) {
            $this->requirePermission(Permission::AgentApprove);
        }

        abort_if($plan->status !== AgentPlanStatus::Proposed, 422, 'This plan has already been decided.');
    }
}
