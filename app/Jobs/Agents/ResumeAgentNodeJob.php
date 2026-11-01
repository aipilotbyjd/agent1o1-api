<?php

namespace App\Jobs\Agents;

use App\Enums\Queue;
use App\Models\Runs\NodeRun;
use App\Services\Workflows\WorkflowRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Continues a workflow's Agent node whose agent paused for approvals, once
 * they are all decided: see `WorkflowRunner::resumeAgentNode()`. On the
 * node-execution queue, like the node itself.
 */
class ResumeAgentNodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly string $nodeRunId)
    {
        $this->onQueue(Queue::WorkflowExecute->value);
    }

    public function handle(WorkflowRunner $runner): void
    {
        $nodeRun = NodeRun::query()->find($this->nodeRunId);

        if ($nodeRun !== null) {
            $runner->resumeAgentNode($nodeRun);
        }
    }
}
