<?php

namespace App\Services;

use App\Enums\ExecutionMode;
use App\Enums\ExecutionStatus;
use App\Jobs\ExecuteWorkflowJob;
use App\Models\Run;
use App\Models\Trigger;
use App\Models\TriggerEvent;
use App\Models\User;
use App\Models\Workflow;

class ExecutionService
{
    /**
     * Start a manual execution of a workflow.
     */
    public function trigger(Workflow $workflow, ?User $user, array $triggerData = [], ExecutionMode $mode = ExecutionMode::Manual): Run
    {
        $execution = Run::create([
            'workflow_id' => $workflow->id,
            'workspace_id' => $workflow->workspace_id,
            'status' => ExecutionStatus::Pending,
            'mode' => $mode,
            'triggered_by' => $user?->id,
            'trigger_data' => $triggerData,
        ]);

        $workflow->incrementExecutionCount();

        ExecuteWorkflowJob::dispatch($execution->id);

        return $execution;
    }

    /**
     * Start an execution from a trigger event (webhook / polling / schedule).
     */
    public function triggerFromEvent(Trigger $trigger, TriggerEvent $event): Run
    {
        $mode = match ($trigger->type) {
            'webhook' => ExecutionMode::Webhook,
            'polling' => ExecutionMode::Polling,
            'scheduled' => ExecutionMode::Scheduled,
            default => ExecutionMode::Manual,
        };

        return $this->trigger($trigger->workflow, null, $event->event_data ?? [], $mode);
    }

    public function retry(Run $execution, ?User $user): Run
    {
        $retry = Run::create([
            'workflow_id' => $execution->workflow_id,
            'workspace_id' => $execution->workspace_id,
            'status' => ExecutionStatus::Pending,
            'mode' => $execution->mode,
            'triggered_by' => $user?->id,
            'trigger_data' => $execution->trigger_data,
            'parent_execution_id' => $execution->id,
            'attempt' => $execution->attempt + 1,
        ]);

        // A retry is a fresh, credit-consuming run — count it like any other
        // execution so execution_count stays consistent with success_rate.
        $execution->workflow->incrementExecutionCount();

        ExecuteWorkflowJob::dispatch($retry->id);

        return $retry;
    }

    public function cancel(Run $execution): Run
    {
        if ($execution->status->isTerminal()) {
            return $execution;
        }

        $execution->update([
            'status' => ExecutionStatus::Cancelled,
            'finished_at' => now(),
        ]);

        return $execution->fresh();
    }
}
