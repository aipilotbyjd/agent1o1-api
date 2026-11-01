<?php

namespace App\Services\Workflows\Engine;

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Jobs\Workflows\DispatchNextNodesJob;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\Workflows\Workflow;
use Illuminate\Support\Arr;

/**
 * Loop Mode as real child `runs` rows, one per item, not an in-handler batch:
 * see docs/WORKFLOWS_PLAN.md's `LoopCoordinator` entry. Each item runs
 * `config.workflow_id` as a child run (`input: {item, index}`); up to
 * `max_concurrent` run at once, releasing the next queued item as an earlier
 * one settles (`resume()`). `on_item_error` governs a mid-batch failure:
 * `fail_fast` fails the loop as soon as one item fails, releasing no further
 * items and cancelling those already in flight; `continue` tolerates item
 * failures and always succeeds once every item has settled; `collect_errors`
 * runs every item to completion (doesn't stop early) but fails the loop
 * overall if any item errored.
 *
 * Progress lives in `NodeRun.state` (docs/WORKFLOWS_AGENTS_BUILD_PLAN.md
 * Stage 4's `add_state_to_node_runs_table` migration) while the loop node's
 * own status stays `Running`: there's no dedicated table for this
 * bookkeeping, and it's never queried outside this class.
 */
class LoopCoordinator
{
    public function __construct(
        private readonly StartWorkflowRunAction $startWorkflowRun,
        private readonly StepFailureHandler $failureHandler,
        private readonly RunCanceller $canceller,
    ) {}

    /**
     * @param  array{key: string, type: string, config: array<string, mixed>}  $nodeDefinition
     * @param  array<string, mixed>  $context
     */
    public function start(Run $run, NodeRun $nodeRun, array $nodeDefinition, array $context): void
    {
        $config = $nodeDefinition['config'] ?? [];
        $items = array_values((array) Arr::get($context, $config['items_path'] ?? '', []));
        // Scoped to the run's own workspace: `workflow_id` is plain config, and
        // an unscoped lookup would let one workspace run another's published
        // workflow, and read its output back through this node.
        $childWorkflow = Workflow::query()
            ->whereKey($config['workflow_id'] ?? null)
            ->where('workspace_id', $run->workspace_id)
            ->first();

        if ($childWorkflow === null || ! $childWorkflow->isPublished()) {
            $this->fail($run, $nodeRun, 'The referenced sub-workflow does not exist or is not published.');

            return;
        }

        if ($items === []) {
            $nodeRun->forceFill([
                'status' => NodeRunStatus::Completed,
                'output' => ['results' => [], 'errors' => []],
                'started_at' => now(),
                'finished_at' => now(),
            ])->save();

            DispatchNextNodesJob::afterSettling($run->id, $nodeRun->id);

            return;
        }

        $nodeRun->forceFill([
            'status' => NodeRunStatus::Running,
            'started_at' => now(),
            'state' => [
                'items' => $items,
                'workflow_id' => $childWorkflow->id,
                'max_concurrent' => max(1, (int) ($config['max_concurrent'] ?? 1)),
                'on_item_error' => $config['on_item_error'] ?? 'fail_fast',
                'next_index' => 0,
                'results' => [],
                'errors' => [],
                'failed' => false,
            ],
        ])->save();

        $this->releaseNext($nodeRun->fresh());
    }

    public function resume(Run $childRun, NodeRun $nodeRun): void
    {
        // Already finished (e.g. fail_fast already tripped by a sibling, or
        // a replayed settle event): later-arriving siblings are no-ops.
        if ($nodeRun->status !== NodeRunStatus::Running) {
            return;
        }

        $state = $nodeRun->state;
        $index = (string) $childRun->loop_index;

        if ($childRun->status === RunStatus::Completed) {
            $state['results'][$index] = $childRun->output;
        } else {
            $state['errors'][$index] = $childRun->error;

            if (($state['on_item_error'] ?? 'fail_fast') === 'fail_fast') {
                $state['failed'] = true;
            }
        }

        // Persisted before releaseNext() runs, since (with the sync queue
        // driver) releasing the next item can synchronously cascade back
        // into resume() for that item before this call returns; that nested
        // call must see this item already recorded, not a stale copy.
        $nodeRun->forceFill(['state' => $state])->save();

        if ($state['failed']) {
            // `fail_fast` means stop, not "stop starting new ones": items
            // already in flight are cancelled too. Leaving them would keep
            // siblings running (and billing) to produce results for a loop
            // node that has already settled and will never read them.
            //
            // Safe to do mid-`resume()`: nothing resumes a parent on a
            // *cancelled* child (only the completed/failed listeners do), so
            // this can't re-enter and record a cancelled item as an error.
            $this->canceller->cancelChildRunsOfNode($nodeRun);

            $this->finish($nodeRun->fresh());

            return;
        }

        $this->releaseNext($nodeRun->fresh());

        // Re-read after releaseNext(): a synchronous cascade may have
        // recorded further results/errors (or already finished the loop)
        // since $state was captured above.
        $nodeRun = $nodeRun->fresh();

        if ($nodeRun->status !== NodeRunStatus::Running) {
            return;
        }

        $state = $nodeRun->state;
        $settled = count($state['results']) + count($state['errors']);

        if ($settled >= count($state['items'])) {
            $this->finish($nodeRun);
        }
    }

    /**
     * Starts as many queued items as available concurrency slots allow.
     * Re-reads state fresh before *every* release (not just once up front)
     * since starting a child can synchronously cascade all the way back
     * into a nested `releaseNext()` call under the sync queue driver: a
     * stale local `$state` would re-release an already-started item.
     */
    private function releaseNext(NodeRun $nodeRun): void
    {
        while (true) {
            $nodeRun = $nodeRun->fresh();

            if ($nodeRun->status !== NodeRunStatus::Running) {
                return;
            }

            $state = $nodeRun->state;

            if ($state['failed'] ?? false) {
                return;
            }

            $running = Run::where('parent_node_id', $nodeRun->id)
                ->whereNotIn('status', [RunStatus::Completed, RunStatus::Failed, RunStatus::Cancelled])
                ->count();

            if ($running >= $state['max_concurrent'] || $state['next_index'] >= count($state['items'])) {
                return;
            }

            $index = $state['next_index'];
            $item = $state['items'][$index];
            $state['next_index']++;

            // Saved before starting the child, for the same reason noted on
            // resume() above.
            $nodeRun->forceFill(['state' => $state])->save();

            $this->startWorkflowRun->execute(
                Workflow::find($state['workflow_id']),
                ['item' => $item, 'index' => $index],
                triggerType: 'loop',
                parentNode: $nodeRun,
                loopIndex: $index,
            );
        }
    }

    private function finish(NodeRun $nodeRun): void
    {
        if ($nodeRun->status !== NodeRunStatus::Running) {
            return;
        }

        $state = $nodeRun->state;
        $run = $nodeRun->run;
        $onItemError = $state['on_item_error'] ?? 'fail_fast';
        $hasErrors = count($state['errors']) > 0;

        if ($hasErrors && $onItemError !== 'continue') {
            $this->fail($run, $nodeRun, 'One or more loop items failed.', $state);

            return;
        }

        $nodeRun->forceFill([
            'status' => NodeRunStatus::Completed,
            'output' => ['results' => $state['results'], 'errors' => $state['errors']],
            'finished_at' => now(),
        ])->save();

        DispatchNextNodesJob::afterSettling($run->id, $nodeRun->id);
    }

    /**
     * @param  array<string, mixed>|null  $state
     */
    private function fail(Run $run, NodeRun $nodeRun, string $message, ?array $state = null): void
    {
        $nodeRun->forceFill([
            'status' => NodeRunStatus::Failed,
            'error' => $message,
            'output' => $state !== null ? ['results' => $state['results'], 'errors' => $state['errors']] : null,
            'started_at' => $nodeRun->started_at ?? now(),
            'finished_at' => now(),
        ])->save();

        $this->failureHandler->routeFailureOrFailRun($run, $nodeRun, $run->workflowVersion->graph);
    }
}
