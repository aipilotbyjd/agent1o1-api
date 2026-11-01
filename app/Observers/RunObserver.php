<?php

namespace App\Observers;

use App\Events\Runs\RunStateChanged;
use App\Models\Runs\Run;

/**
 * Publishes run state to subscribers. Lives on the model rather than in the
 * engine because a run's status is written from half a dozen places
 * (`StartWorkflowRunAction`, `GraphAdvancer`, `StepFailureHandler`,
 * `WorkflowRunner::cancel()`, `AgentRunner`, `RetryRunAction`): an observer
 * covers all of them, and any future one, without each having to remember.
 */
class RunObserver
{
    public function created(Run $run): void
    {
        RunStateChanged::dispatch($run);
    }

    public function updated(Run $run): void
    {
        // Only transitions are interesting; a run whose `output` was written
        // in the same save as its status still reports once, and an update
        // that never touched the status stays off the wire entirely.
        if ($run->wasChanged('status')) {
            RunStateChanged::dispatch($run);
        }
    }
}
