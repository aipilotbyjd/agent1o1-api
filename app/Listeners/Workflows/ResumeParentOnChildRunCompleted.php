<?php

namespace App\Listeners\Workflows;

use App\Events\Runs\RunCompleted;
use App\Services\Workflows\WorkflowRunner;

/**
 * A `subflow`/`loop` node starts a child `runs` row with `parent_node_id`
 * set: this is how the parent finds out the child is done, whether the
 * child settled synchronously (sync queue driver) or much later via a real
 * worker. See `SubWorkflowCoordinator`/`LoopCoordinator`.
 */
class ResumeParentOnChildRunCompleted
{
    public function __construct(private readonly WorkflowRunner $runner) {}

    public function handle(RunCompleted $event): void
    {
        if ($event->run->parent_node_id !== null) {
            $this->runner->resolveSubWorkflow($event->run);
        }
    }
}
