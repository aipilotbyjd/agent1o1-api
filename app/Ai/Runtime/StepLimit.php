<?php

namespace App\Ai\Runtime;

use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\FinishReason;

/**
 * A turn that ran out of steps (`MaxSteps`, or the agent's own
 * `max_steps`) before it finished. The SDK's loop only ends on a tool-call
 * step when the step budget is spent, so the reply is often empty or
 * half-done: the message is marked `stop_reason = step_limit` so the chat
 * can say so and offer to continue, instead of the turn just stopping.
 *
 * A turn that paused for approval also ends on its tool calls, and is not
 * a step-limit stop: it is checked first.
 */
final class StepLimit
{
    public const string REASON = 'step_limit';

    /**
     * Added to a subagent's result when it stopped this way, so the parent
     * doesn't take a half-done answer for a finished one.
     */
    public const string SUBAGENT_NOTE = '[This subagent reached its step limit before finishing, so its work may be incomplete.]';

    public static function reached(AgentResponse $response): bool
    {
        if ($response->hasPendingApprovals()) {
            return false;
        }

        return $response->steps->last()?->finishReason === FinishReason::ToolCalls;
    }
}
