<?php

namespace App\Jobs\Agents;

use App\Enums\Queue;
use App\Exceptions\RunStateException;
use App\Models\Agents\AgentPlan;
use App\Services\Agents\AgentRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Tells the agent its plan was approved and to carry it out now: a normal
 * turn in the plan's conversation, on the queue, so approving a long plan
 * doesn't hold the request open. The chat follows along through the
 * conversation's broadcasts.
 */
class ExecuteApprovedPlanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly string $planId)
    {
        $this->onQueue(Queue::AiAgent->value);
    }

    public function handle(AgentRunner $runner): void
    {
        $plan = AgentPlan::query()->with('session')->find($this->planId);

        if ($plan?->session === null) {
            return;
        }

        $message = "Your plan \"{$plan->title}\" was approved. Carry out its steps now.";

        if (filled($plan->decision_note)) {
            $message .= " Note from the reviewer: {$plan->decision_note}";
        }

        try {
            $runner->run($plan->session, $message, 'plan');
        } catch (RunStateException) {
            // The conversation is busy with another turn; the person can say "go ahead" themselves.
        }
    }
}
