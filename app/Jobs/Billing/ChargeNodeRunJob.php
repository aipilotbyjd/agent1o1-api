<?php

namespace App\Jobs\Billing;

use App\Actions\Billing\DeductCreditsAction;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\Queue;
use App\Models\Runs\NodeRun;
use App\Services\Billing\CreditMeter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Charges one workflow step as soon as it finishes, so the workspace's
 * balance is current while a long run is still going. The same charge the
 * end-of-run listener (`RecordRunCreditUsage`) would make, with the same
 * source, so whichever runs second finds it already on the ledger and
 * charges nothing.
 */
class ChargeNodeRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly string $nodeRunId)
    {
        $this->onQueue(Queue::Billing->value);
        $this->afterCommit();
    }

    public function handle(DeductCreditsAction $deductCredits, CreditMeter $meter): void
    {
        $nodeRun = NodeRun::query()->with('run.workspace')->find($this->nodeRunId);
        $workspace = $nodeRun?->run?->workspace;

        if ($nodeRun === null || $workspace === null || $nodeRun->finished_at === null) {
            return;
        }

        $deductCredits->execute(
            $workspace,
            CreditTransactionType::NodeRun,
            $nodeRun->id,
            $meter->costForNodeRun($nodeRun),
            "Node '{$nodeRun->key}' ({$nodeRun->type})",
            allowOverdraft: true,
        );
    }
}
