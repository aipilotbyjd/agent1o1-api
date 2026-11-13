<?php

namespace App\Jobs\System;

use App\Enums\NodeRunStatus;
use App\Enums\Queue;
use App\Enums\RunStatus;
use App\Enums\Workflows\FlowControlNodeType;
use App\Models\Runs\NodeRun;
use App\Services\Workflows\Engine\StepFailureHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;

/**
 * Scheduled every five minutes (`routes/console.php`): the durability net
 * for a workflow step whose worker died mid-execution: a job killed at its
 * timeout, an out-of-memory kill, a container stopped before it could drain.
 * `ExecuteNodeJob` has no `failed()` that can run in those cases, and the
 * queue never retries (`tries` is 1), so the `NodeRun` would stay `running`
 * and hold its whole `Run` open forever.
 *
 * A node left running far longer than the execute queue's job timeout is
 * handed to the same retry-or-fail policy a step that threw goes through
 * (`StepFailureHandler`), so a node configured with retries is retried and
 * any other fails the run (or follows its `error` edge).
 *
 * `loop` and `subflow` nodes are skipped: they legitimately stay `running`
 * for as long as their child runs do, and those children are runs of their
 * own that this job covers.
 */
class FailStuckNodeRunsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const STUCK_AFTER_MINUTES = 15;

    public function __construct()
    {
        $this->onQueue(Queue::Maintenance->value);
    }

    public function handle(StepFailureHandler $failures): void
    {
        NodeRun::query()
            ->where('status', NodeRunStatus::Running)
            ->whereNotIn('type', [FlowControlNodeType::Loop->value, FlowControlNodeType::SubWorkflow->value])
            ->where('started_at', '<', now()->subMinutes(self::STUCK_AFTER_MINUTES))
            ->whereHas('run', fn ($runs) => $runs->where('status', RunStatus::Running))
            ->with('run.workflowVersion')
            ->each(function (NodeRun $nodeRun) use ($failures): void {
                $graph = $nodeRun->run->workflowVersion->graph;
                $definition = collect($graph['nodes'])->firstWhere('key', $nodeRun->key)
                    ?? ['key' => $nodeRun->key, 'type' => $nodeRun->type, 'config' => []];

                $failures->handle(
                    $nodeRun->run,
                    $nodeRun,
                    $definition,
                    $graph,
                    new RuntimeException('The step stopped before finishing: its worker was lost or timed out.'),
                );
            });
    }
}
