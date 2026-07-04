<?php

namespace App\Observers;

use App\Models\Workflow;
use App\Services\ActivityLogService;

class WorkflowObserver
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function created(Workflow $workflow): void
    {
        $this->activity->log($workflow->workspace_id, 'workflow.created', $workflow, "Created workflow “{$workflow->name}”.");
    }

    public function updated(Workflow $workflow): void
    {
        $this->activity->log($workflow->workspace_id, 'workflow.updated', $workflow, "Updated workflow “{$workflow->name}”.", [
            'changed' => array_keys($workflow->getChanges()),
        ]);
    }

    public function deleted(Workflow $workflow): void
    {
        $this->activity->log($workflow->workspace_id, 'workflow.deleted', $workflow, "Deleted workflow “{$workflow->name}”.");
    }
}
