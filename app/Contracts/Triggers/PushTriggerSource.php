<?php

namespace App\Contracts\Triggers;

use App\Exceptions\ConnectorException;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;

/**
 * A provider whose changes can start a trigger the moment they happen, instead
 * of waiting for the next poll.
 *
 * The poll path of the same trigger stays in place: it is the fallback while
 * push is down and the reconciliation that catches anything push missed.
 *
 * Two kinds, by what the provider sends:
 * - ItemPushSource: the event carries the item itself (Slack messages).
 * - WatchedTriggerSource: the provider only says "something changed" through a
 *   watch we registered, and the trigger's own poll fetches what (Gmail,
 *   Outlook, Drive, Calendar, GitHub). That reuses the poll's filters, ordering
 *   and dedupe, so push and poll can never disagree.
 *
 * @see docs/APP_TRIGGERS_PLAN.md "Delivery mechanisms"
 */
interface PushTriggerSource
{
    /**
     * The provider key events are filed under (`provider_events.provider`).
     */
    public function provider(): string;

    /**
     * Whether this provider's push is switched on and configured here.
     */
    public function isEnabled(): bool;

    /**
     * Whether the trigger is one this source can serve.
     */
    public function supports(Trigger $trigger): bool;

    /**
     * What provider events are matched on for this trigger (a Slack team id, a
     * mailbox address, a channel id), or null when it can't be known.
     *
     * A watched source reads it from the trigger's watch. Anything else may
     * call the provider, so this only runs from a queued job.
     *
     * @throws ConnectorException when the account needs reconnecting
     */
    public function routingKeyFor(Trigger $trigger, ?ProviderWatch $watch = null): ?string;

    /**
     * Something that will stop events arriving even though the subscription
     * exists (the app is not in the channel), in words for the trigger's owner.
     * Null when nothing is wrong or it can't be told.
     */
    public function accessWarning(Trigger $trigger): ?string;
}
