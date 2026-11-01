<?php

namespace App\Listeners\Workflows;

use App\Events\Runs\RunFailed;
use App\Services\Workflows\WorkflowRunner;

class ResumeParentOnChildRunFailed
{
    public function __construct(private readonly WorkflowRunner $runner) {}

    public function handle(RunFailed $event): void
    {
        if ($event->run->parent_node_id !== null) {
            $this->runner->resolveSubWorkflow($event->run);
        }
    }
}
