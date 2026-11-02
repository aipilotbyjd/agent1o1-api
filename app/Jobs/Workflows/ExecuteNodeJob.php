<?php

namespace App\Jobs\Workflows;

use App\Ai\Observability\AiCallContext;
use App\Enums\Queue;
use App\Models\Runs\NodeRun;
use App\Services\Workflows\WorkflowRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Executes exactly one `NodeRun` via `WorkflowRunner::executeStep()`. Kept
 * separate from `DispatchNextNodesJob` (its own `Queue::WorkflowExecute`
 * queue/supervisor) so a burst of cheap node executions can't starve
 * (or be starved by) the traversal work that decides what runs next. See
 * docs/STRUCTURE.md's "Queues & Horizon" table.
 */
class ExecuteNodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $nodeRunId)
    {
        $this->onQueue(Queue::WorkflowExecute->value);
    }

    public function handle(WorkflowRunner $runner): void
    {
        $nodeRun = NodeRun::find($this->nodeRunId);

        if ($nodeRun === null) {
            return;
        }

        app(AiCallContext::class)->run([
            'workspace_id' => $nodeRun->run?->workspace_id,
            'user_id' => $nodeRun->run?->triggered_by,
            'feature' => 'workflow_node',
            'run_id' => $nodeRun->run_id,
            'subject_type' => 'node_run',
            'subject_id' => $nodeRun->id,
        ], fn () => $runner->executeStep($nodeRun));
    }
}
