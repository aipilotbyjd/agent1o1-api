<?php

namespace App\Services\Agents\Approvals;

use App\Enums\Agents\ActionEffect;
use App\Enums\Agents\ActionToolKind;
use App\Enums\Agents\ActionVerdict;
use App\Enums\Agents\AgentActionStatus;
use App\Models\Agents\AgentAction;
use Closure;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Tools\Request;
use Throwable;

/**
 * One tool's link to the gate, built by `ToolRegistry` for every gated tool
 * in a turn. The SDK asks it twice per call: `approvalFor()` before running
 * (anything the gate says to ask pauses the turn), then `run()` in place of
 * the tool's own work, which only really executes when the call was
 * allowed or approved, and only once: a call that already ran hands back
 * its stored result, so resuming a turn can never repeat it.
 */
class ActionGuard
{
    /**
     * @param  array<string, mixed>|null  $policy
     */
    public function __construct(
        private readonly ActionGate $gate,
        private readonly PlanTracker $plans,
        public readonly ActionContext $context,
        private readonly string $toolName,
        private readonly ActionToolKind $kind,
        private readonly ?array $policy = null,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $effectiveArguments
     */
    public function approvalFor(Request $request, array $arguments, array $effectiveArguments, ActionEffect $effect): Approval|false
    {
        $decision = $this->weigh($request, $arguments, $effectiveArguments, $effect);

        if ($decision->verdict !== ActionVerdict::Ask) {
            return false;
        }

        return Approval::required($decision->detail());
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $effectiveArguments
     * @param  Closure(array<string, mixed>): string  $execute  runs the tool with the given model arguments
     */
    public function run(Request $request, array $arguments, array $effectiveArguments, ActionEffect $effect, Closure $execute): string
    {
        $action = $this->weigh($request, $arguments, $effectiveArguments, $effect)->action;

        if ($action === null) {
            return $execute($arguments);
        }

        return match ($action->status) {
            AgentActionStatus::Running, AgentActionStatus::Approved => $this->execute($action, $execute),
            AgentActionStatus::Executed => (string) $action->result,
            AgentActionStatus::Pending => ActionMessages::pending($action),
            AgentActionStatus::Denied => ActionMessages::denied($action),
            AgentActionStatus::Simulated => ActionMessages::simulated($action, $effectiveArguments),
            AgentActionStatus::Failed => ActionMessages::failed($action),
            AgentActionStatus::Rejected, AgentActionStatus::Expired, AgentActionStatus::Cancelled => ActionMessages::rejected($action),
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $effectiveArguments
     */
    private function weigh(Request $request, array $arguments, array $effectiveArguments, ActionEffect $effect): GateDecision
    {
        return $this->gate->weigh($this->context, new ToolCall(
            toolName: $this->toolName,
            kind: $this->kind,
            effect: $effect,
            arguments: $arguments,
            effectiveArguments: $effectiveArguments,
            toolCallId: $request->toolCallId(),
            policy: $this->policy,
        ));
    }

    /**
     * An approved call is claimed before it runs, so two resumes racing on
     * the same turn can't both execute it.
     *
     * @param  Closure(array<string, mixed>): string  $execute
     */
    private function execute(AgentAction $action, Closure $execute): string
    {
        if ($action->status === AgentActionStatus::Approved) {
            $claimed = AgentAction::query()
                ->whereKey($action->id)
                ->where('status', AgentActionStatus::Approved)
                ->update(['status' => AgentActionStatus::Running, 'updated_at' => now()]);

            if ($claimed === 0) {
                return (string) ($action->fresh()?->result ?? ActionMessages::pending($action));
            }
        }

        try {
            $result = $execute($action->effectiveArguments());
        } catch (Throwable $e) {
            $action->forceFill([
                'status' => AgentActionStatus::Failed,
                'result' => $e->getMessage(),
                'executed_at' => now(),
            ])->save();

            throw $e;
        }

        $action->forceFill([
            'status' => AgentActionStatus::Executed,
            'result' => $result,
            'executed_at' => now(),
        ])->save();

        $this->plans->recordExecuted($action);

        return $result;
    }
}
