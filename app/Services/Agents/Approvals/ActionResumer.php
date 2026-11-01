<?php

namespace App\Services\Agents\Approvals;

use App\Enums\RunStatus;
use App\Jobs\Agents\ResumeAgentNodeJob;
use App\Jobs\Agents\ResumeAgentTurnJob;
use App\Models\Agents\AgentAction;
use App\Models\Agents\AgentMessage;
use App\Models\Runs\Run;
use Illuminate\Support\Collection;

/**
 * Hands a paused turn back to the agent once nothing in it is waiting any
 * more. A turn paused on several calls resumes only after the last one is
 * decided: the SDK needs a decision for every one of them at once.
 */
class ActionResumer
{
    public function __construct(private readonly AgentActionExecutor $executor) {}

    /**
     * Queues a resume for every paused turn among `$actions` that is now
     * fully decided.
     *
     * @param  Collection<int, AgentAction>  $actions
     */
    public function resumeReady(Collection $actions): void
    {
        $actions
            ->filter(fn (AgentAction $action): bool => $action->agent_message_id !== null)
            ->unique('agent_message_id')
            ->each(function (AgentAction $action): void {
                if (! $this->isReady($action)) {
                    return;
                }

                if ($action->node_run_id !== null) {
                    ResumeAgentNodeJob::dispatch($action->node_run_id);
                } elseif ($action->run_id !== null) {
                    ResumeAgentTurnJob::dispatch($action->run_id);
                }
            });
    }

    public function isReady(AgentAction $action): bool
    {
        return $this->messageIsReady(AgentMessage::query()->find($action->agent_message_id));
    }

    /**
     * Whether a chat turn paused on approvals can continue now.
     */
    public function runIsReady(Run $run): bool
    {
        return $run->status === RunStatus::AwaitingApproval
            && $this->messageIsReady(AgentMessage::query()->find($run->output['message_id'] ?? null));
    }

    private function messageIsReady(?AgentMessage $message): bool
    {
        if ($message === null || $message->paused_state === null) {
            return false;
        }

        return $this->executor->pausedActions($message)->every(fn (AgentAction $paused): bool => ! $paused->status->isAwaitingDecision());
    }
}
