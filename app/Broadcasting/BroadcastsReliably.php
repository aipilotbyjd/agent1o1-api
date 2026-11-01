<?php

namespace App\Broadcasting;

/**
 * For events that reach Reverb through the queue rather than inline.
 *
 * A broadcast that fails once is gone: a deploy or a restart of Reverb drops
 * every event in that window. Retrying covers it. `afterCommit` stops a
 * broadcast from running before the transaction that created its model has
 * committed (the worker would not find the row), or at all if it rolled back.
 * A queue of their own keeps them from waiting behind mail and maintenance.
 *
 * Retries can deliver an older event after a newer one. Payloads that describe
 * a state carry `emittedAt()`, taken when the change happened, so a subscriber
 * can drop an event older than the one it already shows.
 */
trait BroadcastsReliably
{
    /**
     * Milliseconds since the epoch, for the payload's `emitted_at`.
     */
    private function emittedAt(): int
    {
        return (int) (microtime(true) * 1000);
    }

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [1, 5];

    public string $broadcastQueue = 'broadcasts';

    public bool $afterCommit = true;
}
