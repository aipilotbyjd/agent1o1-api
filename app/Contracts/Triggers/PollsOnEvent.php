<?php

namespace App\Contracts\Triggers;

use App\Models\Triggers\Trigger;

/**
 * An item push source that, for some triggers, only uses an event as a sign
 * to look now: the event carries too little (Slack's "a file was shared" has
 * a file id and nothing else), so the trigger's own poll fetches the full
 * item with its filters, order and dedupe.
 */
interface PollsOnEvent
{
    /**
     * Whether this provider event should make the trigger poll, instead of
     * being delivered as an item.
     *
     * @param  array<string, mixed>  $event  the provider event payload
     */
    public function wantsPoll(Trigger $trigger, array $event): bool;
}
