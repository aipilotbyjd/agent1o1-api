<?php

namespace App\Notifications\Agents;

use App\Enums\Notifications\NotificationType;
use App\Models\Agents\AgentSessionEvaluation;
use App\Notifications\AppNotification;

/**
 * Fired the moment a `SessionEvaluator` run grades `needs_attention`: a
 * `notify`-priority criterion failed. The "Notify" alert: it
 * reaches the agent's owners/admins, never the end user who was chatting
 * with the agent.
 */
class SessionEvaluationNotifyNotification extends AppNotification
{
    public function __construct(AgentSessionEvaluation $evaluation)
    {
        $agent = $evaluation->agent;
        $failed = collect($evaluation->criteria_results ?? [])
            ->where('result', 'failure')
            ->pluck('name')
            ->implode(', ');

        parent::__construct(
            workspace: $evaluation->workspace,
            type: NotificationType::SessionEvaluationNotify,
            title: "Session needs attention for {$agent->name}",
            body: $failed !== '' ? "Failed: {$failed}" : $evaluation->summary,
            data: [
                'agent_id' => $agent->id,
                'agent_session_id' => $evaluation->agent_session_id,
                'agent_session_evaluation_id' => $evaluation->id,
                'grade' => $evaluation->grade->value,
            ],
        );
    }
}
