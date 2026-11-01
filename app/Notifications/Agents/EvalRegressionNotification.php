<?php

namespace App\Notifications\Agents;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Agents\AgentEvalRun;
use App\Notifications\Workspace\WorkspaceEventNotification;

class EvalRegressionNotification extends WorkspaceEventNotification
{
    public function __construct(AgentEvalRun $evalRun)
    {
        $suite = $evalRun->suite;
        $agent = $suite->agent;
        $total = $evalRun->passed + $evalRun->failed;

        parent::__construct(
            workspace: $suite->workspace,
            event: NotificationEvent::EvalRegressed,
            title: "{$agent->name} got worse on \"{$suite->name}\"",
            body: "After the latest change it passed {$evalRun->passed} of {$total} cases, fewer than before.",
            data: [
                'agent_id' => $agent->id,
                'agent_eval_suite_id' => $suite->id,
                'agent_eval_run_id' => $evalRun->id,
                'passed' => $evalRun->passed,
                'failed' => $evalRun->failed,
            ],
        );
    }
}
