<?php

namespace App\Observers;

use App\Enums\Triggers\TriggerTargetType;
use App\Exceptions\PlanLimitExceededException;
use App\Exceptions\WorkflowValidationException;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Triggers\TriggerNodeSync;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Keeps a workflow's `triggers` rows in step with the workflow being trashed
 * or brought back, not only with it being published (TriggerNodeSync).
 *
 * A trashed workflow's triggers would otherwise stay on: counting toward the
 * plan's active triggers, taking in webhooks that then run nothing, and
 * recording skipped events until the skip streak switches them off.
 */
class WorkflowObserver
{
    public function __construct(private readonly TriggerNodeSync $triggerSync) {}

    /**
     * Remove the workflow's triggers. They are made from the published graph,
     * so restoring the workflow makes them again. Runs for a soft and a
     * permanent delete alike.
     */
    public function deleted(Workflow $workflow): void
    {
        Trigger::query()
            ->where('target_type', TriggerTargetType::Workflow->value)
            ->where('target_id', $workflow->id)
            ->each(fn (Trigger $trigger) => $trigger->delete());
    }

    /**
     * Make the triggers of the published version again.
     *
     * A trigger that can't be made (its account is gone, or the plan's active
     * triggers are used up) doesn't undo the restore: the workflow comes back,
     * and publishing it again reports the problem where the person can fix it.
     */
    public function restored(Workflow $workflow): void
    {
        $nodes = $workflow->currentVersion?->graph['nodes'] ?? [];

        if ($nodes === []) {
            return;
        }

        $actor = Auth::user();

        try {
            $this->triggerSync->sync($workflow, $nodes, $actor instanceof User ? $actor : $workflow->creator);
        } catch (WorkflowValidationException|PlanLimitExceededException $exception) {
            Log::warning('Triggers were not restored with the workflow.', ['workflow_id' => $workflow->id, 'error' => $exception->getMessage()]);
        }
    }
}
