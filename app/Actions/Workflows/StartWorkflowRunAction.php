<?php

namespace App\Actions\Workflows;

use App\Enums\RunStatus;
use App\Exceptions\WorkflowValidationException;
use App\Jobs\Workflows\DispatchNextNodesJob;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Billing\CreditGate;

/**
 * Creates a `runs` row pinned to the workflow's `current_version_id`, never
 * the live draft, so editing a workflow never changes what an in-flight run
 * executes (docs/WORKFLOWS_PLAN.md), and dispatches the entry node(s).
 * Called from the Internal/Public API `RunController`s, `SubWorkflowCoordinator`/
 * `LoopCoordinator` (child runs, `$parentNode` set) and the trigger pipeline
 * (`App\Services\Triggers\TargetRunStarter`, docs/TRIGGERS_PLAN.md).
 *
 * Top-level runs go through `CreditGate` first, so a workspace out of credits
 * is refused before any node executes rather than after the LLM spend has
 * already happened. Child runs (`$parentNode` set) are exempt: see below.
 */
class StartWorkflowRunAction
{
    public function __construct(private readonly CreditGate $creditGate) {}

    /**
     * `$entryNodeKey` names the trigger node a trigger-fired run starts
     * from: the graph's other trigger nodes are skipped (see
     * `GraphAdvancer::startEntries()`). Null starts every entry node.
     *
     * @param  array<string, mixed>  $input
     */
    public function execute(
        Workflow $workflow,
        array $input = [],
        ?User $triggeredBy = null,
        string $triggerType = 'manual',
        ?NodeRun $parentNode = null,
        ?int $loopIndex = null,
        ?string $entryNodeKey = null,
    ): Run {
        if (! $workflow->isPublished()) {
            throw new WorkflowValidationException(['The workflow must be published before it can run.']);
        }

        // Only top-level runs are gated. A sub-workflow/loop child run is part
        // of work the parent already passed the gate for, and refusing it
        // mid-flight would strand the parent run rather than save any spend.
        if ($parentNode === null) {
            $this->creditGate->assertCanStartWorkflowRun($workflow->workspace);
        }

        // `status`/`started_at` aren't in Run's #[Fillable] list (engine-
        // managed, not user-mass-assignable): create() would silently drop
        // them, so create first, then forceFill the status transition.
        $run = $workflow->runs()->create([
            'workspace_id' => $workflow->workspace_id,
            'runnable_type' => $workflow->getMorphClass(),
            'runnable_id' => $workflow->id,
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $workflow->current_version_id,
            'trigger_type' => $triggerType,
            'entry_node_key' => $entryNodeKey,
            'input' => $input,
            'triggered_by' => $triggeredBy?->id,
            'parent_run_id' => $parentNode?->run_id,
            'parent_node_id' => $parentNode?->id,
            'loop_index' => $loopIndex,
        ]);

        $run->forceFill(['status' => RunStatus::Running, 'started_at' => now()])->save();

        DispatchNextNodesJob::dispatch($run->id);

        // With the sync queue driver the whole run may already have
        // completed synchronously by the time dispatch() returns: reload
        // so callers (controllers building a response right away) see the
        // current state instead of the "running" snapshot from above.
        return $run->fresh();
    }
}
