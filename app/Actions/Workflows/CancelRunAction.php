<?php

namespace App\Actions\Workflows;

use App\Exceptions\RunStateException;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Workflows\WorkflowRunner;

/**
 * Stops an in-flight run from the API surface. Cancellation always applies to
 * the whole run *tree*, not just the row that was asked for: a `subflow`/
 * `loop` child run whose parent kept going would strand the parent forever
 * (it resumes on the child's terminal event, and a cancelled child never
 * produces the output the parent node is waiting on), so this walks up to the
 * root run first and cancels downward from there.
 */
class CancelRunAction
{
    public function __construct(private readonly WorkflowRunner $runner) {}

    /**
     * @throws RunStateException when the run has already reached a terminal state
     */
    public function execute(Run $run, ?User $cancelledBy = null): Run
    {
        if ($run->status->isTerminal()) {
            throw RunStateException::alreadyFinished();
        }

        $this->runner->cancel($this->rootOf($run), $cancelledBy);

        // The caller asked about *this* run, not the root: return its own
        // post-cancellation state.
        return $run->fresh();
    }

    /**
     * Walks `parent_run_id` to the top of the tree. Bounded by the set of
     * runs already visited so a cyclic pointer (which the engine never
     * writes, but a bad backfill could) can't spin forever.
     */
    private function rootOf(Run $run): Run
    {
        $seen = [$run->id => true];

        while (($parent = $run->parentRun) !== null && ! isset($seen[$parent->id])) {
            $seen[$parent->id] = true;
            $run = $parent;
        }

        return $run;
    }
}
