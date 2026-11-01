<?php

namespace App\Services\Agents\Approvals;

use App\Enums\Agents\AgentActionStatus;
use App\Models\Agents\AgentAction;
use App\Models\Agents\AgentMessage;
use Illuminate\Support\Collection;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

/**
 * Settles a paused turn's decided calls before the model hears about them:
 * each approved call is run here, through its own tool (so it goes through
 * the same guard, bound-field filter and billing as any call), and its
 * result stored on the action. Only then is the turn resumed: the SDK's
 * own re-run of an approved call finds the stored result and hands it back
 * rather than running it again (`ActionGuard::run()`).
 *
 * Running them here first means an approved action happens exactly once
 * and its outcome is recorded even if the model call that follows fails.
 */
class AgentActionExecutor
{
    /**
     * The asked calls of a paused turn, in the order the model made them.
     *
     * @return Collection<int, AgentAction>
     */
    public function pausedActions(AgentMessage $message): Collection
    {
        $pendingIds = $message->paused_state['pending_tool_call_ids'] ?? [];

        return AgentAction::query()
            ->where('agent_message_id', $message->id)
            ->whereIn('tool_call_id', $pendingIds)
            ->oldest('id')
            ->get();
    }

    /**
     * Runs every approved call and returns each decided call's result, in
     * the stored `tool_results` shape, keyed by tool call id.
     *
     * @param  Collection<int, AgentAction>  $actions
     * @param  array<int, mixed>  $tools  the turn's tools, from `ToolRegistry`
     * @return array<string, array{id: string, name: string, arguments: array<string, mixed>, result: string, result_id: null}>
     */
    public function settle(Collection $actions, array $tools): array
    {
        $results = [];

        foreach ($actions as $action) {
            if ($action->status === AgentActionStatus::Approved) {
                $this->runApproved($action, $tools);
                $action->refresh();
            }

            $results[$action->tool_call_id] = [
                'id' => $action->tool_call_id,
                'name' => $action->tool_name,
                'arguments' => $action->effectiveArguments(),
                'result' => $this->resultText($action),
                'result_id' => null,
            ];
        }

        return $results;
    }

    /**
     * What the SDK is told about each call. A call that ran (or failed) is
     * "approved": the SDK re-runs it through the guard, which returns the
     * stored outcome. A rejection carries its explanation as the result so
     * the model can adapt, unless the person asked to stop, which ends the
     * turn there.
     *
     * @param  Collection<int, AgentAction>  $actions
     */
    public function decisionsFor(Collection $actions): Decisions
    {
        return Decisions::from($actions->mapWithKeys(fn (AgentAction $action): array => [
            $action->tool_call_id => match (true) {
                in_array($action->status, [AgentActionStatus::Executed, AgentActionStatus::Failed, AgentActionStatus::Running], true) => Decision::approve(),
                $action->stops_turn => Decision::reject(),
                default => Decision::reject(ActionMessages::rejectionText($action->status, $action->decision_note)),
            },
        ])->all());
    }

    /**
     * @param  array<int, mixed>  $tools
     */
    private function runApproved(AgentAction $action, array $tools): void
    {
        $tool = collect($tools)->first(fn (mixed $tool): bool => $tool instanceof Tool && $tool->name() === $action->tool_name);

        if ($tool === null) {
            $action->forceFill([
                'status' => AgentActionStatus::Failed,
                'result' => 'The tool is no longer attached to this agent.',
                'executed_at' => now(),
            ])->save();

            return;
        }

        try {
            $tool->handle(new Request($action->effectiveArguments(), $action->tool_call_id));
        } catch (Throwable) {
            // The guard has already recorded the failure on the action.
        }
    }

    private function resultText(AgentAction $action): string
    {
        return match ($action->status) {
            AgentActionStatus::Executed => (string) $action->result,
            AgentActionStatus::Failed => ActionMessages::failed($action),
            default => ActionMessages::rejectionText($action->status, $action->decision_note),
        };
    }
}
