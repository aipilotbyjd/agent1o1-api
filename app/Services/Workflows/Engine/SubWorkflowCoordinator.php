<?php

namespace App\Services\Workflows\Engine;

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Jobs\Workflows\DispatchNextNodesJob;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\Workflows\Workflow;

/**
 * The `subflow` node type: starts a child `runs` row against another
 * `Workflow` and pauses the parent node until the child settles, then
 * resumes (or fails) it. See docs/WORKFLOWS_PLAN.md's `SubWorkflowCoordinator`
 * entry.
 *
 * Resumption is event-driven (`Listeners\Workflows\ResumeParentOnChildRun{Completed,Failed}`
 * → `WorkflowRunner::resolveSubWorkflow()`), not polled: this works
 * identically whether the child settles synchronously (sync queue driver, in
 * tests) or much later via real queue workers.
 */
class SubWorkflowCoordinator
{
    public function __construct(
        private readonly StartWorkflowRunAction $startWorkflowRun,
        private readonly StepFailureHandler $failureHandler,
    ) {}

    /**
     * @param  array{key: string, type: string, config: array<string, mixed>}  $nodeDefinition
     */
    public function start(Run $run, NodeRun $nodeRun, array $nodeDefinition): void
    {
        $config = $nodeDefinition['config'] ?? [];
        // Scoped to the run's own workspace: `workflow_id` is plain config, and
        // an unscoped lookup would let one workspace run another's published
        // workflow, and read its output back through this node.
        $childWorkflow = Workflow::query()
            ->whereKey($config['workflow_id'] ?? null)
            ->where('workspace_id', $run->workspace_id)
            ->first();

        if ($childWorkflow === null || ! $childWorkflow->isPublished()) {
            $this->failImmediately($run, $nodeRun, 'The referenced sub-workflow does not exist or is not published.');

            return;
        }

        $nodeRun->forceFill(['status' => NodeRunStatus::Running, 'started_at' => now()])->save();

        // {{ }} templating (docs/WORKFLOWS_AGENTS_BUILD_PLAN.md Stage 5) will
        // resolve `config.input` against the run context: a literal object
        // is all that's supported for now.
        $this->startWorkflowRun->execute($childWorkflow, $config['input'] ?? [], parentNode: $nodeRun);
    }

    public function resume(Run $childRun, NodeRun $nodeRun): void
    {
        // Idempotency: the listener fires once per child settle event, but a
        // child that was already resolved (e.g. a replayed event) must not
        // re-advance the parent a second time.
        if ($nodeRun->status !== NodeRunStatus::Running) {
            return;
        }

        $run = $nodeRun->run;

        if ($childRun->status === RunStatus::Completed) {
            $nodeRun->forceFill(['status' => NodeRunStatus::Completed, 'output' => $childRun->output, 'finished_at' => now()])->save();
            DispatchNextNodesJob::afterSettling($run->id, $nodeRun->id);

            return;
        }

        $nodeRun->forceFill([
            'status' => NodeRunStatus::Failed,
            'error' => $childRun->error ?? 'Sub-workflow failed.',
            'finished_at' => now(),
        ])->save();

        $this->failureHandler->routeFailureOrFailRun($run, $nodeRun, $run->workflowVersion->graph);
    }

    private function failImmediately(Run $run, NodeRun $nodeRun, string $message): void
    {
        $nodeRun->forceFill([
            'status' => NodeRunStatus::Failed,
            'error' => $message,
            'started_at' => now(),
            'finished_at' => now(),
        ])->save();

        $this->failureHandler->routeFailureOrFailRun($run, $nodeRun, $run->workflowVersion->graph);
    }
}
