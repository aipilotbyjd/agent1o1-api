<?php

namespace App\Observers;

use App\Events\Runs\NodeRunStateChanged;
use App\Models\Runs\NodeRun;

/**
 * The per-step half of `RunObserver`. Note that this only fires for saves
 * that go through the model: the engine's bulk cancellations were converted
 * to per-model saves (`WorkflowRunner::cancel()`,
 * `StepFailureHandler::failRun()`) precisely so a watching canvas doesn't
 * miss a node going grey.
 */
class NodeRunObserver
{
    public function created(NodeRun $nodeRun): void
    {
        NodeRunStateChanged::dispatch($nodeRun);
    }

    public function updated(NodeRun $nodeRun): void
    {
        if ($nodeRun->wasChanged('status')) {
            NodeRunStateChanged::dispatch($nodeRun);
        }
    }
}
