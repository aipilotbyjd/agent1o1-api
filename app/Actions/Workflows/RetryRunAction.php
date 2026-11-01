<?php

namespace App\Actions\Workflows;

use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Exceptions\RunStateException;
use App\Jobs\Workflows\DispatchNextNodesJob;
use App\Jobs\Workflows\ExecuteNodeJob;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Billing\CreditGate;
use Illuminate\Support\Facades\DB;

/**
 * Re-executes a finished run as a brand-new `Run`, never by mutating the
 * original, whose `node_runs` and credit-ledger entries are the record of
 * what actually happened.
 *
 * The retry pins to the *original's* `workflow_version_id`, not the
 * workflow's current version: a retry reproduces the run that failed, and
 * pinning to `current_version_id` would silently execute a different graph
 * than the one being retried (and break `from_node_key`, whose node might no
 * longer exist). Publishing a fix and running that is a normal new run, not a
 * retry.
 *
 * `trigger_type` carries over for the same reason: among other things it
 * decides whether pinned node data short-circuits execution
 * (`WorkflowRunner::executeNodeContract()`), so a retried webhook run must
 * stay a webhook run rather than quietly becoming a manual, pin-using one.
 */
class RetryRunAction
{
    public function __construct(private readonly CreditGate $creditGate) {}

    /**
     * @param  string|null  $fromNodeKey  resume from this node, carrying over
     *                                    the original run's settled state for
     *                                    everything upstream of it; null
     *                                    re-runs the graph from its entries
     *
     * @throws RunStateException
     */
    public function execute(Run $run, ?User $triggeredBy = null, ?string $fromNodeKey = null): Run
    {
        if (! $run->status->isTerminal()) {
            throw RunStateException::stillRunning();
        }

        if (! $run->isWorkflowRun() || $run->workflow_version_id === null) {
            throw RunStateException::notRetryable();
        }

        $graph = $run->workflowVersion->graph;
        $nodeKeys = array_map(fn (array $node): string => $node['key'], $graph['nodes']);

        if ($fromNodeKey !== null && ! in_array($fromNodeKey, $nodeKeys, true)) {
            throw RunStateException::unknownNodeKey($fromNodeKey);
        }

        $this->creditGate->assertCanStartWorkflowRun($run->workspace);

        $retry = DB::transaction(function () use ($run, $graph, $fromNodeKey, $triggeredBy): Run {
            $retry = Run::create([
                'workspace_id' => $run->workspace_id,
                'runnable_type' => $run->runnable_type,
                'runnable_id' => $run->runnable_id,
                'workflow_id' => $run->workflow_id,
                'workflow_version_id' => $run->workflow_version_id,
                'retried_from_run_id' => $run->id,
                'trigger_type' => $run->trigger_type,
                'entry_node_key' => $run->entry_node_key,
                'input' => $run->input ?? [],
                'triggered_by' => $triggeredBy?->id,
            ]);

            $retry->forceFill(['status' => RunStatus::Running, 'started_at' => now()])->save();

            if ($fromNodeKey !== null) {
                $this->carryOverUpstream($run, $retry, $graph, $fromNodeKey);
            }

            return $retry;
        });

        if ($fromNodeKey === null) {
            DispatchNextNodesJob::dispatch($retry->id);

            return $retry->fresh();
        }

        $resumed = $retry->nodeRuns()->create([
            'key' => $fromNodeKey,
            'type' => collect($graph['nodes'])->firstWhere('key', $fromNodeKey)['type'],
            'input' => $retry->input,
        ]);

        ExecuteNodeJob::dispatch($resumed->id);

        // With the sync queue driver the retry may already be finished by the
        // time dispatch() returns: reload rather than hand back the
        // "running" snapshot taken above.
        return $retry->fresh();
    }

    /**
     * Copies the original run's settled `node_runs` into the retry for every
     * node that is neither the resume point nor downstream of it, so the
     * resumed node sees the same upstream context it saw the first time
     * (`WorkflowRunner::buildContext()` reads completed `node_runs`) without
     * re-executing (and paying for) work that already succeeded.
     *
     * Downstream rows are deliberately *not* copied: they are what the retry
     * is going to produce again.
     *
     * @param  array{nodes: array<int, array{key: string, type: string, config: array<string, mixed>}>, edges: array<int, array{from: string, to: string, condition: string|null}>}  $graph
     */
    private function carryOverUpstream(Run $run, Run $retry, array $graph, string $fromNodeKey): void
    {
        $excluded = $this->descendantKeys($graph, $fromNodeKey);
        $excluded[$fromNodeKey] = true;

        $carried = $run->nodeRuns()
            ->whereNotIn('key', array_keys($excluded))
            ->whereIn('status', [NodeRunStatus::Completed, NodeRunStatus::Skipped, NodeRunStatus::Failed])
            ->get();

        foreach ($carried as $nodeRun) {
            $copy = $retry->nodeRuns()->create([
                'key' => $nodeRun->key,
                'type' => $nodeRun->type,
                'input' => $nodeRun->input,
                'max_attempts' => $nodeRun->max_attempts,
                'retry_delay_seconds' => $nodeRun->retry_delay_seconds,
            ]);

            // Timings are copied verbatim rather than stamped `now()`: this
            // row is a record of work done during the original run, and
            // pretending it happened during the retry would misreport the
            // retry's duration.
            $copy->forceFill([
                'status' => $nodeRun->status,
                'output' => $nodeRun->output,
                'usage' => $nodeRun->usage,
                'state' => $nodeRun->state,
                'error' => $nodeRun->error,
                'attempt' => $nodeRun->attempt,
                'started_at' => $nodeRun->started_at,
                'finished_at' => $nodeRun->finished_at,
            ])->save();
        }
    }

    /**
     * Every node reachable from `$fromNodeKey`, following error edges too:
     * anything the resumed node can lead to must be re-derived.
     *
     * @param  array{nodes: array<int, array{key: string, type: string, config: array<string, mixed>}>, edges: array<int, array{from: string, to: string, condition: string|null}>}  $graph
     * @return array<string, true>
     */
    private function descendantKeys(array $graph, string $fromNodeKey): array
    {
        $descendants = [];
        $queue = [$fromNodeKey];

        while ($queue !== []) {
            $key = array_shift($queue);

            foreach ($graph['edges'] as $edge) {
                if ($edge['from'] !== $key || isset($descendants[$edge['to']])) {
                    continue;
                }

                $descendants[$edge['to']] = true;
                $queue[] = $edge['to'];
            }
        }

        return $descendants;
    }
}
