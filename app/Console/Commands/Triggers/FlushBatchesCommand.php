<?php

namespace App\Console\Commands\Triggers;

use App\Enums\Triggers\TriggerEventStatus;
use App\Models\Triggers\Trigger;
use App\Services\Triggers\TriggerService;
use Illuminate\Console\Command;

/**
 * Runs the events a trigger has been holding as one, once its window is over:
 * its group window (`config.batch_seconds`) since the oldest arrived, or its
 * quiet period (`config.debounce_seconds`) since the newest.
 *
 * A trigger that has stopped holding flushes what it still holds straight
 * away, so nothing is left waiting. See TriggerService::heldWindowIsOver().
 */
class FlushBatchesCommand extends Command
{
    protected $signature = 'triggers:flush-batches';

    protected $description = 'Run the events triggers have been holding as one run each.';

    /**
     * Flush every trigger whose window is over.
     */
    public function handle(TriggerService $triggers): int
    {
        $flushed = 0;

        Trigger::query()
            ->where('is_active', true)
            ->whereHas('events', fn ($events) => $events->where('status', TriggerEventStatus::Held->value))
            ->eachById(function (Trigger $trigger) use ($triggers, &$flushed): void {
                if (! $triggers->heldWindowIsOver($trigger)) {
                    return;
                }

                if ($triggers->flushHeld($trigger) !== null) {
                    $flushed++;
                }
            });

        $this->info("Ran {$flushed} group(s) of held events.");

        return self::SUCCESS;
    }
}
