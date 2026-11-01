<?php

namespace App\Notifications\Agents;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Agents\ReflectionRun;
use App\Notifications\Workspace\WorkspaceEventNotification;

class ReflectionReportNotification extends WorkspaceEventNotification
{
    public function __construct(ReflectionRun $reflectionRun)
    {
        $agent = $reflectionRun->agent;
        $proposed = $reflectionRun->reflections()->count();

        parent::__construct(
            workspace: $reflectionRun->workspace,
            event: NotificationEvent::ReflectionRunCompleted,
            title: "Reflection completed for {$agent->name}",
            body: $reflectionRun->status->value === 'skipped'
                ? "Skipped: {$reflectionRun->skip_reason}"
                : "Reviewed {$reflectionRun->sessions_analyzed_count} session(s) and proposed {$proposed} improvement(s).",
            data: [
                'agent_id' => $agent->id,
                'reflection_run_id' => $reflectionRun->id,
                'status' => $reflectionRun->status->value,
                'reflections_proposed' => $proposed,
            ],
        );
    }
}
