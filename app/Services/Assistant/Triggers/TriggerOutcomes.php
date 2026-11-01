<?php

namespace App\Services\Assistant\Triggers;

use App\Enums\Assistant\AssistantTriggerStatus;
use App\Enums\Assistant\AssistantTurnStatus;
use App\Models\Assistant\AssistantTurn;
use App\Notifications\Assistant\TriggerDisabledNotification;

/**
 * Counts a trigger's failed runs in a row; past the limit it switches off
 * and tells the owner, instead of failing on every tick forever.
 */
class TriggerOutcomes
{
    public function record(AssistantTurn $turn): void
    {
        $trigger = $turn->session?->trigger;

        if ($trigger === null) {
            return;
        }

        if ($turn->status === AssistantTurnStatus::Completed) {
            $trigger->forceFill(['consecutive_failures' => 0])->save();

            return;
        }

        if ($turn->status !== AssistantTurnStatus::Failed) {
            return;
        }

        $failures = $trigger->consecutive_failures + 1;
        $limit = (int) config('assistant.triggers.max_consecutive_failures');

        $trigger->forceFill([
            'consecutive_failures' => $failures,
            'status' => $failures >= $limit ? AssistantTriggerStatus::Disabled : $trigger->status,
        ])->save();

        if ($failures === $limit) {
            $trigger->assistant->user->notify(new TriggerDisabledNotification($trigger, (string) $turn->error));
        }
    }
}
