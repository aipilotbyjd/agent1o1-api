<?php

namespace App\Services\Agents;

use App\Ai\Runtime\StepLimit;
use App\Enums\Agents\SubagentTaskStatus;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Agents\SubagentTask;

/**
 * Keeps a subagent's `SubagentTask` in step with its conversation's turn,
 * which `AgentRunner` settles, including a turn that pauses for approvals
 * and finishes much later, long after `RunSubagentTaskJob` has returned.
 * A no-op for any conversation that isn't a subagent's.
 */
class SubagentTaskTracker
{
    public function awaitApproval(AgentSession $session): void
    {
        if ($session->parent_session_id === null) {
            return;
        }

        SubagentTask::query()
            ->where('session_id', $session->id)
            ->where('status', SubagentTaskStatus::Running)
            ->update(['status' => SubagentTaskStatus::AwaitingApproval]);
    }

    public function complete(AgentSession $session, AgentMessage $reply): void
    {
        if ($session->parent_session_id === null) {
            return;
        }

        SubagentTask::query()
            ->where('session_id', $session->id)
            ->whereIn('status', [SubagentTaskStatus::Running, SubagentTaskStatus::AwaitingApproval])
            ->update([
                'status' => SubagentTaskStatus::Completed,
                'result' => $reply->stop_reason === StepLimit::REASON
                    ? trim($reply->content."\n\n".StepLimit::SUBAGENT_NOTE)
                    : $reply->content,
                'finished_at' => now(),
            ]);
    }
}
