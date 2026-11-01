<?php

namespace App\Services\Assistant\Briefings;

use App\Actions\Billing\DeductCreditsAction;
use App\Enums\Assistant\AssistantBriefingRunStatus;
use App\Enums\Billing\CreditTransactionType;
use App\Events\Assistant\AssistantBriefingCompleted;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantBriefingRun;
use App\Services\Billing\CreditMeter;
use Throwable;

/**
 * The lifecycle every background report shares: claimed once, charged
 * once, delivered once, and its end announced to the owner's open screens.
 */
trait SettlesBriefingRuns
{
    /**
     * `queued` → `collecting`, as a conditional update so a duplicate job
     * can never run the same report twice.
     */
    private function claim(AssistantBriefingRun $run): bool
    {
        return AssistantBriefingRun::query()
            ->whereKey($run->id)
            ->where('status', AssistantBriefingRunStatus::Queued)
            ->update(['status' => AssistantBriefingRunStatus::Collecting, 'updated_at' => now()]) === 1;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function finish(AssistantBriefingRun $run, AssistantBriefingRunStatus $status, array $attributes = []): void
    {
        $run->forceFill(['status' => $status, ...$attributes])->save();

        // Live refresh is a convenience: the report is saved either way, so a
        // broadcasting outage must not fail it.
        try {
            AssistantBriefingCompleted::dispatch($run);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<string, mixed>|null  $usage
     */
    private function charge(AssistantBriefingRun $run, Assistant $assistant, ?array $usage, string $reason): void
    {
        if ($usage === null) {
            return;
        }

        app(DeductCreditsAction::class)->execute(
            $assistant->workspace,
            CreditTransactionType::AssistantTurn,
            $run->id,
            app(CreditMeter::class)->costForAssistantTurn($usage),
            $reason,
            allowOverdraft: true,
        );
    }

    /**
     * True for exactly one caller per report: whoever gets to deliver it.
     */
    private function claimDelivery(AssistantBriefingRun $run): bool
    {
        return AssistantBriefingRun::query()
            ->whereKey($run->id)
            ->whereNull('delivered_at')
            ->update(['delivered_at' => now()]) === 1;
    }
}
