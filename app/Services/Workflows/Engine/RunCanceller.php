<?php

namespace App\Services\Workflows\Engine;

use App\Enums\Agents\AgentActionStatus;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Events\Runs\RunCancelled;
use App\Models\Agents\AgentAction;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Everything that has to be settled when a run stops before its graph is
 * finished. Extracted from `WorkflowRunner::cancel()` so the *failure* paths:
 * `StepFailureHandler::failRun()` and `LoopCoordinator`'s `fail_fast`:
 * can reuse it without depending on `WorkflowRunner`, which would close a
 * dependency cycle (`WorkflowRunner` → `StepFailureHandler` → back again).
 *
 * A run that stops early leaves two kinds of debris behind, and both matter
 * however it stopped:
 *
 * - Node runs parked on a person (`awaiting_approval`) or on an inbound
 *   webhook (`awaiting_callback`). Their callback URL keeps resolving until
 *   the token is nulled, so an approval on an already-dead run stays
 *   actionable and a `Wait` node stays resumable.
 * - Child runs a `subflow`/`loop` node started. Those keep executing (and
 *   keep billing) to produce output whose parent node has already settled
 *   and will never read it.
 */
class RunCanceller
{
    /**
     * Stops a run: every in-flight `NodeRun` is settled as `cancelled`, the
     * run itself becomes `cancelled`, and the same is done recursively to
     * any child run a `subflow`/`loop` node started. Callers that want the
     * *whole* tree stopped (the API does) should hand this the root run;
     * `CancelRunAction` resolves it.
     *
     * Idempotent: cancelling an already-terminal run is a no-op returning
     * the run untouched, so a double-clicked button can't fail loudly.
     */
    public function cancel(Run $run, ?User $cancelledBy = null): Run
    {
        $cancelled = DB::transaction(function () use ($run): ?Run {
            $locked = Run::whereKey($run->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isTerminal()) {
                return null;
            }

            $this->settleInFlightNodeRuns($locked);

            // An agent action still waiting on a person must not be
            // approvable, and run, after its run has been stopped.
            AgentAction::query()
                ->where('run_id', $locked->id)
                ->where('status', AgentActionStatus::Pending)
                ->update([
                    'status' => AgentActionStatus::Cancelled,
                    'decided_at' => now(),
                    'decision_note' => 'The run was cancelled.',
                    'updated_at' => now(),
                ]);

            $locked->forceFill([
                'status' => RunStatus::Cancelled,
                'error' => 'Run cancelled.',
                'finished_at' => now(),
            ])->save();

            return $locked;
        });

        if ($cancelled === null) {
            return $run->fresh();
        }

        $this->cancelChildRuns($cancelled, $cancelledBy);

        event(new RunCancelled($cancelled, $cancelledBy));

        return $cancelled;
    }

    /**
     * Settles every `NodeRun` that hasn't reached a terminal state as
     * `cancelled`: the two parked states included, or a paused
     * `HumanApproval`/`Wait` node would outlive the run it belongs to.
     *
     * Leaves already-terminal rows alone, so the node that *caused* a
     * failure keeps its own `failed` status and error message.
     */
    public function settleInFlightNodeRuns(Run $run): void
    {
        // Saved one model at a time rather than as a bulk `update()`: a
        // query-builder update fires no model events, and a watching canvas
        // would never see these nodes go grey (`NodeRunObserver`). Bounded
        // by the graph's node count, so the cost is trivial.
        //
        // `callback_token` is nulled alongside the status so a `Wait` node's
        // public callback URL stops resolving the moment its run stops,
        // exactly as `WorkflowRunner::resolveCallback()` does on use.
        $inFlight = $run->nodeRuns()
            ->whereIn('status', NodeRunStatus::inFlight())
            ->get();

        foreach ($inFlight as $nodeRun) {
            $nodeRun->forceFill([
                'status' => NodeRunStatus::Cancelled,
                'finished_at' => now(),
                'callback_token' => null,
            ])->save();
        }
    }

    /**
     * Cancels every in-flight run started beneath `$run`: one level down
     * here, but `cancel()` recurses, so a whole nested tree unwinds.
     */
    public function cancelChildRuns(Run $run, ?User $cancelledBy = null): void
    {
        foreach ($run->childRuns()->whereIn('status', RunStatus::inFlight())->get() as $childRun) {
            $this->cancel($childRun, $cancelledBy);
        }
    }

    /**
     * The narrower cut `LoopCoordinator` needs: only the runs *this* node
     * started (`parent_node_id`), not every child of the run it belongs to.
     * A loop that stops early has siblings of its own to clean up while the
     * rest of the parent run carries on down an error edge.
     */
    public function cancelChildRunsOfNode(NodeRun $nodeRun, ?User $cancelledBy = null): void
    {
        $children = Run::where('parent_node_id', $nodeRun->id)
            ->whereIn('status', RunStatus::inFlight())
            ->get();

        foreach ($children as $childRun) {
            $this->cancel($childRun, $cancelledBy);
        }
    }
}
