<?php

namespace App\Services\Workflows\Engine;

use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Enums\Workflows\FlowControlNodeType;
use App\Events\Runs\RunCompleted;
use App\Jobs\Workflows\ExecuteNodeJob;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\Workflows\WorkflowEdge;
use App\Services\Triggers\TriggerNodes;
use App\Services\Workflows\StepOptions;
use App\Services\Workflows\WorkflowIo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Drives traversal of the immutable `WorkflowVersion.graph` snapshot a `Run`
 * is pinned to (never the live, editable `WorkflowNode`/`WorkflowEdge`
 * rows: see docs/WORKFLOWS_PLAN.md's `workflow_versions` note on why).
 * Ported design from docs/WORKFLOWS_PLAN.md's `Services/Workflows/Engine/
 * GraphAdvancer` section; ERROR_CONDITION lives on `WorkflowEdge` since the
 * graph snapshot's edges use the same string.
 *
 * @phpstan-type NodeShape array{key: string, type: string, config: array<string, mixed>}
 * @phpstan-type EdgeShape array{from: string, to: string, condition: string|null}
 * @phpstan-type GraphShape array{nodes: array<int, array{key: string, type: string, config: array<string, mixed>}>, edges: array<int, array{from: string, to: string, condition: string|null}>}
 */
class GraphAdvancer
{
    /**
     * Mirrors `NodeRunStatus::isTerminal()`: kept as a literal array here
     * since it's used inside a query builder clause, not against a hydrated
     * enum instance.
     *
     * @var array<int, NodeRunStatus>
     */
    private const array TERMINAL_NODE_RUN_STATUSES = [
        NodeRunStatus::Completed,
        NodeRunStatus::Failed,
        NodeRunStatus::Skipped,
        NodeRunStatus::Cancelled,
    ];

    /**
     * @param  array{nodes: array<int, array{key: string, type: string, config: array<string, mixed>}>, edges: array<int, array{from: string, to: string, condition: string|null}>}  $graph
     */
    public function startEntries(Run $run, array $graph): void
    {
        if ($run->status->isTerminal()) {
            return;
        }

        $created = DB::transaction(function () use ($run, $graph): array {
            $locked = Run::whereKey($run->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isTerminal()) {
                return [];
            }

            $entryKeys = self::entryKeys($graph);

            if ($run->entry_node_key !== null && in_array($run->entry_node_key, $entryKeys, true)) {
                $entryKeys = $this->startFromTrigger($run, $run->entry_node_key, $entryKeys, $graph);
            }

            return $this->createPending($run, $entryKeys, $graph);
        });

        $this->dispatchCreated($created);

        $this->finishIfDone($run);
    }

    /**
     * A trigger-fired run starts at its own trigger node plus every entry
     * node that isn't a trigger (an Input step, say), not at the graph's
     * other trigger nodes. Everything only those other triggers lead to is
     * recorded `skipped` up front, so a Join Paths fed by both branches sees
     * a settled source instead of waiting forever. A step both branches
     * lead to stays reachable and runs normally, which `skip()`'s recursive
     * propagation would get wrong, hence no reuse of it here.
     *
     * @param  array<int, string>  $entryKeys
     * @param  array{nodes: array<int, array{key: string, type: string, config: array<string, mixed>}>, edges: array<int, array{from: string, to: string, condition: string|null}>}  $graph
     * @return array<int, string>
     */
    private function startFromTrigger(Run $run, string $entryNodeKey, array $entryKeys, array $graph): array
    {
        $typesByKey = collect($graph['nodes'])->pluck('type', 'key');
        $nodesByKey = collect($graph['nodes'])->keyBy('key');
        $triggerNodes = app(TriggerNodes::class);

        $activeKeys = array_values(array_filter(
            $entryKeys,
            fn (string $key): bool => $key === $entryNodeKey || ! $triggerNodes->isTriggerEntry($nodesByKey->get($key)),
        ));

        $reachable = array_fill_keys($activeKeys, true);
        $queue = $activeKeys;

        while ($queue !== []) {
            $from = array_shift($queue);

            foreach ($graph['edges'] as $edge) {
                if ($edge['from'] === $from && ! isset($reachable[$edge['to']])) {
                    $reachable[$edge['to']] = true;
                    $queue[] = $edge['to'];
                }
            }
        }

        foreach ($typesByKey as $key => $type) {
            if (isset($reachable[$key])) {
                continue;
            }

            try {
                $nodeRun = DB::transaction(fn (): NodeRun => $run->nodeRuns()->create(['key' => $key, 'type' => $type, 'input' => $run->input]));
            } catch (UniqueConstraintViolationException) {
                continue;
            }

            $nodeRun->forceFill(['status' => NodeRunStatus::Skipped, 'finished_at' => now()])->save();
        }

        return $activeKeys;
    }

    /**
     * @param  array{nodes: array<int, array{key: string, type: string, config: array<string, mixed>}>, edges: array<int, array{from: string, to: string, condition: string|null}>}  $graph
     */
    public function advance(Run $run, NodeRun $settledNode, array $graph, bool $continueOnError = false): void
    {
        // A run cancelled while this traversal was queued must not sprout
        // new work: `WorkflowRunner::cancel()` already settled everything
        // it could see, and `executeStep()` catches whatever slips past.
        if ($run->status->isTerminal()) {
            return;
        }

        // Everything that creates this node's successors (and the flag
        // clearing that tells `finishIfDone()` it has) runs under the run's
        // row lock, the same lock `finishIfDone()` takes, so a sibling
        // branch can't judge the run finished while only some successors
        // exist.
        $created = DB::transaction(function () use ($run, $settledNode, $graph, $continueOnError): array {
            $locked = Run::whereKey($run->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isTerminal()) {
                return [];
            }

            $outgoing = array_values(array_filter($graph['edges'], fn (array $edge) => $edge['from'] === $settledNode->key));

            $matchedKeys = [];

            // Every sibling `NodeRun` row (pending-to-dispatch or skipped) is
            // created before any of them is actually dispatched, with the sync
            // queue driver, dispatching a job runs it (and everything it in turn
            // triggers, including `finishIfDone`) to completion immediately, so
            // a sibling created *after* the first dispatch would be invisible to
            // that premature `finishIfDone` check and the run could complete
            // while a sibling still sits unprocessed.
            foreach ($outgoing as $edge) {
                if ($this->edgeMatches($edge, $settledNode, $continueOnError)) {
                    $matchedKeys[] = $edge['to'];
                } else {
                    $this->skip($run, $edge['to'], $graph);
                }
            }

            // A matched edge into a not-yet-ready join is silently dropped here
            // (not created, not skipped): the branch that finally makes the
            // join ready is whichever settles last; see joinReadiness().
            $readyKeys = array_values(array_filter(
                $matchedKeys,
                fn (string $key) => $this->joinReadiness($run, $key, $graph) === true,
            ));

            $created = $this->createPending($run, $readyKeys, $graph);

            NodeRun::query()->whereKey($settledNode->id)->update(['pending_advance' => false]);

            return $created;
        });

        $this->dispatchCreated($created);

        $this->finishIfDone($run);
    }

    /**
     * A `join_paths` (Merge) target isn't created-and-dispatched (or
     * skipped) the moment one branch reaches it, only once every one of its
     * incoming (non-error) edges has a *terminal* source `NodeRun`
     * (completed, failed, or skipped: "will never contribute more").
     *
     * Returns `null` while still waiting on a sibling branch, `true` once
     * ready with at least one incoming edge actually matching (dispatch it
     * normally), or `false` once ready but every incoming edge failed to
     * match (skip it: nothing ever arrived). Non-join nodes are always
     * `true`.
     *
     * @param  array{nodes: array<int, array{key: string, type: string, config: array<string, mixed>}>, edges: array<int, array{from: string, to: string, condition: string|null}>}  $graph
     */
    private function joinReadiness(Run $run, string $key, array $graph): ?bool
    {
        $type = collect($graph['nodes'])->firstWhere('key', $key)['type'] ?? null;

        if ($type !== FlowControlNodeType::JoinPaths->value) {
            return true;
        }

        $incoming = collect($graph['edges'])
            ->filter(fn (array $edge) => $edge['to'] === $key && $edge['condition'] !== WorkflowEdge::ERROR_CONDITION);

        if ($incoming->isEmpty()) {
            return true;
        }

        $sourceKeys = $incoming->pluck('from')->unique();
        $sources = $run->nodeRuns()->whereIn('key', $sourceKeys)->get()->keyBy('key');

        if ($sources->count() < $sourceKeys->count()) {
            return null;
        }

        foreach ($sources as $source) {
            if (! in_array($source->status, self::TERMINAL_NODE_RUN_STATUSES, true)) {
                return null;
            }
        }

        foreach ($incoming as $edge) {
            $source = $sources->get($edge['from']);

            // A Skipped source never actually fired this edge: edgeMatches()
            // doesn't know about Skipped (it's only ever called on a settled
            // node that just executed), so it must be excluded explicitly
            // rather than asked to evaluate the condition.
            if ($source !== null && $source->status !== NodeRunStatus::Skipped && $this->edgeMatches($edge, $source)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{from: string, to: string, condition: string|null}  $edge
     */
    private function edgeMatches(array $edge, NodeRun $settledNode, bool $continueOnError = false): bool
    {
        $isErrorEdge = $edge['condition'] === WorkflowEdge::ERROR_CONDITION;

        if ($settledNode->status === NodeRunStatus::Failed) {
            if ($isErrorEdge) {
                return true;
            }

            // `continue_on_error`, no error edge taken: treat the failure as
            // a soft success for unconditional edges only: there's no
            // usable `result` output to match a conditioned edge against.
            return $continueOnError && $edge['condition'] === null;
        }

        if ($isErrorEdge) {
            return false;
        }

        if ($edge['condition'] === null) {
            return true;
        }

        return ($settledNode->output['result'] ?? null) === $edge['condition'];
    }

    /**
     * Idempotent: a unique-constraint race on `(run_id, key)` means another
     * branch already created this `NodeRun`: the loser is a no-op, not a
     * duplicate. See docs/WORKFLOWS_PLAN.md's "Idempotent step creation".
     *
     * @param  array<int, string>  $keys
     * @param  array{nodes: array<int, array{key: string, type: string, config: array<string, mixed>}>, edges: array<int, array{from: string, to: string, condition: string|null}>}  $graph
     */
    private function createPending(Run $run, array $keys, array $graph): array
    {
        $created = [];

        foreach ($keys as $key) {
            $node = collect($graph['nodes'])->firstWhere('key', $key);
            $type = $node['type'] ?? null;

            if ($type === null) {
                continue;
            }

            // The retry knobs are persisted on the row so what the API
            // reports is what the failure handler will actually do.
            $options = StepOptions::fromNodeConfig($node['config'] ?? []);

            try {
                // A savepoint, so losing the unique `(run_id, key)` race
                // doesn't abort the surrounding transaction.
                $created[] = DB::transaction(fn (): NodeRun => $run->nodeRuns()->create([
                    'key' => $key,
                    'type' => $type,
                    'input' => $run->input,
                    'max_attempts' => $options->maxAttempts,
                    'retry_delay_seconds' => $options->retryDelaySeconds,
                ]));
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        return $created;
    }

    /**
     * @param  array<int, NodeRun>  $created
     */
    private function dispatchCreated(array $created): void
    {
        foreach ($created as $nodeRun) {
            ExecuteNodeJob::dispatch($nodeRun->id);
        }
    }

    /**
     * Recursively marks a node (and everything only reachable through it)
     * `skipped`. A `join_paths` target defers to `joinReadiness()` the same
     * way the matched path does: this branch failing to reach it doesn't
     * mean the join itself is dead, since another incoming edge might still
     * match, only skip the join once every incoming source is terminal and
     * none of them matched.
     *
     * @param  array{nodes: array<int, array{key: string, type: string, config: array<string, mixed>}>, edges: array<int, array{from: string, to: string, condition: string|null}>}  $graph
     */
    private function skip(Run $run, string $key, array $graph): void
    {
        $type = collect($graph['nodes'])->firstWhere('key', $key)['type'] ?? null;

        if ($type === null) {
            return;
        }

        if ($type === FlowControlNodeType::JoinPaths->value && $this->joinReadiness($run, $key, $graph) !== false) {
            // Either still waiting on a sibling branch, or a sibling already
            // matched (and dispatched it): nothing for this branch to do.
            return;
        }

        try {
            // `status`/`finished_at` aren't in NodeRun's #[Fillable] list
            // (engine-managed, not user-mass-assignable (see NodeRun's own
            // convention)) create() would silently drop them, so create
            // first, then forceFill the status transition.
            $nodeRun = DB::transaction(fn (): NodeRun => $run->nodeRuns()->create(['key' => $key, 'type' => $type, 'input' => $run->input]));
        } catch (UniqueConstraintViolationException) {
            return;
        }

        $nodeRun->forceFill(['status' => NodeRunStatus::Skipped, 'finished_at' => now()])->save();

        foreach (array_filter($graph['edges'], fn (array $edge) => $edge['from'] === $key) as $edge) {
            $this->skip($run, $edge['to'], $graph);
        }
    }

    /**
     * Completes the run once nothing is left in flight, under
     * `lockForUpdate()` so two branches settling simultaneously can't both
     * observe "nothing pending" and race to complete a run whose sibling
     * node is about to be created. The run's output is its Output steps'
     * values when the graph has any, otherwise every completed step's output.
     */
    public function finishIfDone(Run $run): void
    {
        $completed = DB::transaction(function () use ($run): ?Run {
            $locked = Run::whereKey($run->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isTerminal()) {
                return null;
            }

            // Anything non-terminal counts as in flight, not just
            // Pending/Running but AwaitingApproval/AwaitingCallback too, or
            // a paused HumanApproval/Wait node would let the run complete
            // out from under it.
            // A node that settled but still owes its successors
            // (`pending_advance`) counts too: they don't exist yet.
            $stillInFlight = $locked->nodeRuns()
                ->where(fn ($query) => $query
                    ->whereNotIn('status', self::TERMINAL_NODE_RUN_STATUSES)
                    ->orWhere('pending_advance', true))
                ->exists();

            if ($stillInFlight) {
                return null;
            }

            $completedOutputs = $locked->nodeRuns()
                ->where('status', NodeRunStatus::Completed)
                ->pluck('output', 'key')
                ->all();

            $locked->forceFill([
                'status' => RunStatus::Completed,
                'output' => WorkflowIo::runResult($locked->workflowVersion?->graph ?? [], $completedOutputs) ?? $completedOutputs,
                'finished_at' => now(),
            ])->save();

            return $locked;
        });

        if ($completed !== null) {
            event(new RunCompleted($completed));
        }
    }

    /**
     * An entry node is one with no incoming *healthy-path* edge: mirrors
     * `GraphValidator`'s definition exactly (error-only edges don't count).
     *
     * @param  array{nodes: array<int, array{key: string, type: string, config: array<string, mixed>}>, edges: array<int, array{from: string, to: string, condition: string|null}>}  $graph
     * @return array<int, string>
     */
    public static function entryKeys(array $graph): array
    {
        $hasIncoming = [];

        foreach ($graph['edges'] as $edge) {
            if ($edge['condition'] !== WorkflowEdge::ERROR_CONDITION) {
                $hasIncoming[$edge['to']] = true;
            }
        }

        return array_values(array_filter(
            array_map(fn (array $node) => $node['key'], $graph['nodes']),
            fn (string $key) => ! isset($hasIncoming[$key]),
        ));
    }
}
