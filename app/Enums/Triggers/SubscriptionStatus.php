<?php

namespace App\Enums\Triggers;

/**
 * Where a trigger's provider subscription is in its life.
 *
 * @see docs/APP_TRIGGERS_PLAN.md "Subscription state machine"
 */
enum SubscriptionStatus: string
{
    /**
     * Wanted, not confirmed yet.
     */
    case Pending = 'pending';

    /**
     * Receiving events.
     */
    case Active = 'active';

    /**
     * Push has stopped being trustworthy; polling is covering for it.
     */
    case Degraded = 'degraded';

    /**
     * The connected account needs reconnecting before anything arrives.
     */
    case ReconnectNeeded = 'reconnect_needed';

    /**
     * Permanently unable to subscribe (the resource is gone, access denied).
     */
    case Failed = 'failed';

    /**
     * Whether events arriving for this subscription are routed to its trigger.
     */
    public function receivesEvents(): bool
    {
        return in_array($this, [self::Active, self::Degraded], true);
    }
}
