<?php

namespace App\Jobs\Workflows;

use App\Enums\Queue;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Services\Workflows\Engine\GraphAdvancer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Resolves what's ready to run next for a `Run` and dispatches one
 * `ExecuteNodeJob` per ready node, either from the start (`$settledNodeRunId`
 * null, entry nodes) or after a node settles. Kept on its own queue/
 * supervisor (`Queue::WorkflowDispatch`) separate from `ExecuteNodeJob`'s
 * `Queue::WorkflowExecute`, per docs/STRUCTURE.md's "Queues & Horizon" table.
 */
class DispatchNextNodesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $runId,
        public ?string $settledNodeRunId = null,
        public bool $continueOnError = false,
    ) {
        $this->onQueue(Queue::WorkflowDispatch->value);
    }

    /**
     * Queues the traversal that follows a settled node, flagging the node as
     * still owing its successors until `GraphAdvancer::advance()` has created
     * them, so a sibling branch can't complete the run in between.
     */
    public static function afterSettling(string $runId, string $settledNodeRunId, bool $continueOnError = false): PendingDispatch
    {
        NodeRun::query()->whereKey($settledNodeRunId)->update(['pending_advance' => true]);

        return static::dispatch($runId, $settledNodeRunId, $continueOnError);
    }

    public function handle(GraphAdvancer $advancer): void
    {
        $run = Run::find($this->runId);

        if ($run === null) {
            return;
        }

        $graph = $run->workflowVersion->graph;

        if ($this->settledNodeRunId === null) {
            $advancer->startEntries($run, $graph);

            return;
        }

        $settledNode = NodeRun::find($this->settledNodeRunId);

        if ($settledNode === null) {
            return;
        }

        $advancer->advance($run, $settledNode, $graph, $this->continueOnError);
    }
}
