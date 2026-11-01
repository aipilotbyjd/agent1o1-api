<?php

namespace App\Enums\Triggers;

/**
 * Why the circuit breaker switched a trigger off.
 *
 * @see Trigger::recordFailure()
 * @see Trigger::recordSkip()
 */
enum TriggerDisabledReason: string
{
    /**
     * Too many events in a row failed to start a run.
     */
    case Failures = 'failures';

    /**
     * Too many events in a row were dropped without running: out of credits,
     * or the last run was still going under the "skip" overlap policy.
     */
    case Skips = 'skips';

    /**
     * Far more events arrived in a minute than the trigger's storm limit
     * (`config.storm_limit`, else `triggers.storm.max_events_per_minute`): a
     * loop or a bulk import upstream, switched off before it floods the
     * workspace with runs.
     */
    case Storm = 'storm';
}
