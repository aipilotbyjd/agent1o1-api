<?php

namespace App\Services\Agents\Approvals;

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Ai\Tools\WorkflowTool;
use App\Enums\Agents\ActionToolKind;
use App\Enums\Agents\ActionVerdict;
use App\Enums\Agents\AgentActionStatus;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentAction;
use App\Models\Agents\AgentToolBinding;
use App\Models\Workflows\Workflow;
use Illuminate\Support\Collection;

/**
 * Loosening an agent's tool rules on evidence: "always allow this" from an
 * approval card, and suggestions for tools a person keeps approving
 * unchanged: the agent has earned trust on them.
 *
 * Only node and workflow tools have rules to change; the agent's own
 * built-in tools (editing its instructions or skills) always follow the
 * mode.
 */
class TrustRules
{
    /**
     * How many unedited approvals, with no rejection, make a suggestion.
     */
    public const int SUGGEST_AFTER_APPROVALS = 5;

    public const int SUGGESTION_WINDOW_DAYS = 30;

    public function allowAlways(Agent $agent, string $toolName): bool
    {
        return $this->setMode($agent, $toolName, ActionVerdict::Allow);
    }

    public function setMode(Agent $agent, string $toolName, ActionVerdict $mode): bool
    {
        $binding = $agent->toolBindings()->where('node_type', $toolName)->first();

        if ($binding instanceof AgentToolBinding) {
            $binding->update(['approval_policy' => [...($binding->approval_policy ?? []), 'mode' => $mode->value]]);

            return true;
        }

        $workflow = $this->workflowNamed($agent, $toolName);

        if ($workflow === null) {
            return false;
        }

        $policy = $workflow->pivot?->approval_policy;
        $policy = is_string($policy) ? json_decode($policy, true) : ($policy ?? []);

        $agent->workflows()->updateExistingPivot($workflow->id, [
            'approval_policy' => json_encode([...$policy, 'mode' => $mode->value]),
        ]);

        return true;
    }

    /**
     * Tools this agent asks about that a person has approved, unedited,
     * at least `SUGGEST_AFTER_APPROVALS` times lately and never rejected.
     *
     * @return Collection<int, array{tool_name: string, tool_kind: string, approvals: int}>
     */
    public function suggestionsFor(Agent $agent): Collection
    {
        $actions = $agent->actions()
            ->where('outcome', ActionVerdict::Ask)
            ->whereIn('tool_kind', [ActionToolKind::Node, ActionToolKind::Workflow])
            ->where('created_at', '>=', now()->subDays(self::SUGGESTION_WINDOW_DAYS))
            ->get(['tool_name', 'tool_kind', 'status', 'edited_arguments']);

        return $actions
            ->groupBy('tool_name')
            ->map(function (Collection $calls, string $toolName): ?array {
                $rejected = $calls->contains(fn (AgentAction $action): bool => $action->status === AgentActionStatus::Rejected);
                $approved = $calls->filter(fn (AgentAction $action): bool => in_array($action->status, [AgentActionStatus::Executed, AgentActionStatus::Approved], true)
                    && $action->edited_arguments === null);

                if ($rejected || $approved->count() < self::SUGGEST_AFTER_APPROVALS) {
                    return null;
                }

                return ['tool_name' => $toolName, 'tool_kind' => $calls->first()->tool_kind->value, 'approvals' => $approved->count()];
            })
            ->filter()
            ->reject(fn (array $suggestion): bool => $this->currentMode($agent, $suggestion['tool_name']) === ActionVerdict::Allow)
            ->values();
    }

    private function currentMode(Agent $agent, string $toolName): ?ActionVerdict
    {
        $binding = $agent->toolBindings()->where('node_type', $toolName)->first();

        if ($binding !== null) {
            return ActionVerdict::tryFrom((string) ($binding->approval_policy['mode'] ?? ''));
        }

        $policy = $this->workflowNamed($agent, $toolName)?->pivot?->approval_policy;
        $policy = is_string($policy) ? json_decode($policy, true) : $policy;

        return ActionVerdict::tryFrom((string) ($policy['mode'] ?? ''));
    }

    private function workflowNamed(Agent $agent, string $toolName): ?Workflow
    {
        return $agent->workflows()->get()->first(
            fn (Workflow $workflow): bool => (new WorkflowTool($workflow, app(StartWorkflowRunAction::class)))->name() === $toolName,
        );
    }
}
