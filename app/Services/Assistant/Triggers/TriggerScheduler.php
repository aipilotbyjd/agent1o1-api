<?php

namespace App\Services\Assistant\Triggers;

use App\Enums\Assistant\AssistantTriggerStatus;
use App\Enums\Assistant\AssistantTriggerType;
use App\Models\Assistant\AssistantTrigger;
use Throwable;

/**
 * Fires every schedule and one-time trigger that is due.
 */
class TriggerScheduler
{
    public function __construct(private readonly TriggerFirer $firer) {}

    public function fireDue(): int
    {
        $fired = 0;

        AssistantTrigger::query()
            ->with('assistant')
            ->where('status', AssistantTriggerStatus::Active)
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('type', AssistantTriggerType::Schedule)->where('next_run_at', '<=', now()))
                ->orWhere(fn ($query) => $query->where('type', AssistantTriggerType::Once)->where('run_at', '<=', now())))
            ->each(function (AssistantTrigger $trigger) use (&$fired): void {
                try {
                    if ($this->firer->fire($trigger) !== null) {
                        $fired++;
                    }
                } catch (Throwable $e) {
                    report($e);
                }
            });

        return $fired;
    }
}
