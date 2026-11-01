<?php

namespace App\Enums\Triggers;

/**
 * How a trigger fires.
 *
 * Also used as `trigger_events.source`, the way an event arrived. That is
 * usually the trigger's own type, except that "Run now" records `manual`.
 */
enum TriggerType: string
{
    /**
     * A POST to the trigger's private URL.
     */
    case Webhook = 'webhook';

    /**
     * A cron schedule.
     */
    case Schedule = 'schedule';

    /**
     * A URL or app reader checked on an interval.
     */
    case Polling = 'polling';

    /**
     * Started by a person ("Run now").
     */
    case Manual = 'manual';

    /**
     * Whether this type gets a public URL token. Only webhooks do.
     */
    public function usesToken(): bool
    {
        return $this === self::Webhook;
    }

    /**
     * Whether this type authenticates an outbound request with a connected
     * account. Only polling does.
     */
    public function usesCredential(): bool
    {
        return $this === self::Polling;
    }
}
