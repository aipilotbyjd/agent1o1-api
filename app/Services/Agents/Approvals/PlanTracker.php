<?php

namespace App\Services\Agents\Approvals;

use App\Enums\Agents\AgentPlanStatus;
use App\Models\Agents\AgentAction;
use App\Models\Agents\AgentPlan;
use App\Models\Agents\AgentSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Keeps Plan mode's plans up to date: a new plan replaces any still open in
 * the conversation, an executed step is ticked off, and a plan with nothing
 * left to do completes.
 */
class PlanTracker
{
    /**
     * @param  list<array{tool: string, summary: string, arguments?: array<string, mixed>}>  $steps
     */
    public function propose(AgentSession $session, ?string $runId, string $title, ?string $summary, array $steps): AgentPlan
    {
        return DB::transaction(function () use ($session, $runId, $title, $summary, $steps): AgentPlan {
            $session->plans()
                ->whereIn('status', [AgentPlanStatus::Proposed, AgentPlanStatus::Approved])
                ->update(['status' => AgentPlanStatus::Superseded]);

            return $session->plans()->create([
                'workspace_id' => $session->workspace_id,
                'agent_id' => $session->agent_id,
                'run_id' => $runId,
                'title' => $title,
                'summary' => $summary,
                'steps' => array_map(fn (array $step): array => [
                    'id' => (string) Str::uuid(),
                    'tool' => $step['tool'],
                    'summary' => $step['summary'],
                    'arguments' => $step['arguments'] ?? [],
                    'status' => AgentPlan::STEP_PENDING,
                ], $steps),
            ]);
        });
    }

    public function recordExecuted(AgentAction $action): void
    {
        $stepId = $action->reason['step_id'] ?? null;

        if ($action->plan_id === null || $stepId === null) {
            return;
        }

        DB::transaction(function () use ($action, $stepId): void {
            $plan = AgentPlan::query()->lockForUpdate()->find($action->plan_id);

            if ($plan === null || $plan->status !== AgentPlanStatus::Approved) {
                return;
            }

            $steps = array_map(
                fn (array $step): array => $step['id'] === $stepId ? [...$step, 'status' => AgentPlan::STEP_DONE, 'action_id' => $action->id] : $step,
                $plan->steps,
            );

            $plan->forceFill(['steps' => $steps])->save();

            if ($plan->pendingSteps() === []) {
                $plan->forceFill(['status' => AgentPlanStatus::Completed])->save();
            }
        });
    }
}
