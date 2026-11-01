<?php

namespace App\Notifications\Workspace;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\Workflows\WorkflowApproval;
use App\Models\Workspaces\Workspace;

class RunApprovalRequestedNotification extends WorkspaceEventNotification
{
    public function __construct(Workspace $workspace, Run $run, NodeRun $nodeRun, WorkflowApproval $approval)
    {
        parent::__construct(
            workspace: $workspace,
            event: NotificationEvent::RunApprovalRequested,
            title: "Approval needed in {$workspace->name}",
            body: "Workflow run {$run->id} is waiting for approval at {$nodeRun->key}.",
            data: [
                'run_id' => $run->id,
                'node_run_id' => $nodeRun->id,
                'node_key' => $nodeRun->key,
                'approval_id' => $approval->id,
            ],
        );
    }
}
