<?php

namespace App\Observers;

use App\Events\Runs\NodeRunStateChanged;
use App\Jobs\Billing\ChargeNodeRunJob;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Services\Billing\CreditMeter;
use Throwable;

/**
 * The per-step half of `RunObserver`. Note that this only fires for saves
 * that go through the model: the engine's bulk cancellations were converted
 * to per-model saves (`WorkflowRunner::cancel()`,
 * `StepFailureHandler::failRun()`) precisely so a watching canvas doesn't
 * miss a node going grey.
 */
class NodeRunObserver
{
    public function created(NodeRun $nodeRun): void
    {
        NodeRunStateChanged::dispatch($nodeRun);
    }

    public function updated(NodeRun $nodeRun): void
    {
        if ($nodeRun->wasChanged('status')) {
            NodeRunStateChanged::dispatch($nodeRun);
        }

        if ($nodeRun->wasChanged('finished_at') && $nodeRun->getOriginal('finished_at') === null && $nodeRun->finished_at !== null) {
            $this->accrue($nodeRun);
        }
    }

    /**
     * A step just finished: add what it cost to its run's running total
     * (which the per-run credit budget reads), and charge it now rather than
     * when the whole run ends. Bookkeeping must never fail the step, so an
     * error here is reported and left for the end-of-run charge to cover.
     */
    private function accrue(NodeRun $nodeRun): void
    {
        try {
            $credits = app(CreditMeter::class)->costForNodeRun($nodeRun);

            if ($credits > 0) {
                Run::query()->whereKey($nodeRun->run_id)->increment('credits_accrued', $credits);
            }

            if (config('billing.realtime_node_billing')) {
                ChargeNodeRunJob::dispatch($nodeRun->id);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
