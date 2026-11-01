<?php

namespace App\Enums\Triggers;

/**
 * What became of one inbound event.
 *
 * Events are stored before any work happens, so this status, not whether a
 * run exists, is the record of what happened to each one.
 */
enum TriggerEventStatus: string
{
    /**
     * Stored and waiting for a worker.
     */
    case Queued = 'queued';

    /**
     * A worker is starting its run right now.
     */
    case Running = 'running';

    /**
     * It started a run; `run_id` points at it.
     */
    case Fired = 'fired';

    /**
     * Deliberately not run, and not a failure: the trigger's filters didn't
     * match, or a polling trigger's first poll found the item already there.
     */
    case Ignored = 'ignored';

    /**
     * Dropped without running: the trigger was off, the target can't run
     * (unpublished or deleted), the workspace is out of credits, or the last
     * run was still going under the "skip" overlap policy.
     */
    case Skipped = 'skipped';

    /**
     * The request failed authentication (signature or token).
     */
    case Rejected = 'rejected';

    /**
     * Every retry was spent without starting a run.
     */
    case Failed = 'failed';

    /**
     * A redelivery of an event already accepted.
     *
     * Only ever returned as an intake outcome, never written to the status
     * column: the original row keeps the status it already had.
     */
    case Duplicate = 'duplicate';

    /**
     * Kept back to be run together with the others that arrive in the
     * trigger's "group events" window (`config.batch_seconds`).
     */
    case Held = 'held';

    /**
     * Was run as part of a group: the group's own event started the run.
     */
    case Batched = 'batched';

    /**
     * Whether the event has reached a status it will never leave on its own.
     */
    public function isTerminal(): bool
    {
        return ! in_array($this, [self::Queued, self::Running], true);
    }

    /**
     * Whether a worker may still pick the event up.
     */
    public function isQueueable(): bool
    {
        return $this === self::Queued;
    }

    /**
     * Statuses the system has taken on but not resolved: what the
     * retry-stuck command searches.
     *
     * @return array<int, string>
     */
    public static function unresolved(): array
    {
        return [self::Queued->value, self::Running->value];
    }
}
