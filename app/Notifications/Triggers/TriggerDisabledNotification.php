<?php

namespace App\Notifications\Triggers;

use App\Enums\Notifications\NotificationEvent;
use App\Enums\Triggers\TriggerDisabledReason;
use App\Models\Triggers\Trigger;
use App\Notifications\Workspace\WorkspaceEventNotification;

/**
 * Tells a workspace's owners and admins, and the trigger's creator, that the
 * circuit breaker switched a trigger off, and what to do about it.
 *
 * Sent once, by Trigger::switchOff().
 */
class TriggerDisabledNotification extends WorkspaceEventNotification
{
    /**
     * Build the message for the reason the trigger was switched off.
     */
    public function __construct(Trigger $trigger, TriggerDisabledReason $reason = TriggerDisabledReason::Failures)
    {
        $targetName = $trigger->target?->name ?? 'a deleted '.$trigger->target_type;

        parent::__construct(
            workspace: $trigger->workspace,
            event: NotificationEvent::TriggerDisabled,
            title: "A {$trigger->type->value} trigger on \"{$targetName}\" was switched off",
            body: match ($reason) {
                TriggerDisabledReason::Failures => "It failed {$trigger->consecutive_failure_count} times in a row, so it was switched off to stop the failures. Check its recent events, fix the cause (often an expired account or a changed URL), then switch it back on.",
                TriggerDisabledReason::Storm => "More than {$trigger->stormLimit()} events arrived in a minute, so it was switched off before it filled the workspace with runs. Look for a loop or a bulk import at the source, then switch it back on. A trigger that expects bursts can group them with \"Group events\" or \"Wait for quiet\", or raise its storm limit.",
                TriggerDisabledReason::Skips => "Its last {$trigger->consecutive_skip_count} events were dropped without running (out of credits, or the previous run was still going), so it was switched off. Top up credits or make it fire less often, then switch it back on.",
            },
            data: [
                'trigger_id' => $trigger->id,
                'target_type' => $trigger->target_type,
                'target_id' => $trigger->target_id,
                'reason' => $reason->value,
            ],
        );
    }
}
