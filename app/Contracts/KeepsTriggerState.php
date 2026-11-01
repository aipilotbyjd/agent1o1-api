<?php

namespace App\Contracts;

use App\Models\Runs\Run;

/**
 * A reader node whose trigger can't be found by reading a list and picking
 * out what's new, because the event is something that stopped being there: a
 * row deleted, a file trashed.
 *
 * It remembers something between polls (a snapshot of what was present, or a
 * provider's change token) in the trigger's poll cursor. The first poll only
 * takes that baseline, so switching the trigger on never reports what was
 * already gone. The state is saved only after a poll succeeds, so a failed read
 * is simply repeated.
 *
 * Used instead of triggerItems(): a node implementing this is polled through
 * pollSince(). It still implements SupportsTriggerMode (name, interval, item
 * ids) so the rest of the pipeline treats it like any reader trigger.
 */
interface KeepsTriggerState
{
    /**
     * What happened since `$state` was taken, and the state to keep.
     *
     * At most `triggers.poll_max_items` items should be returned: anything the
     * poll cuts off is never read again, so a node with more waiting leaves the
     * rest in the state it returns.
     *
     * @param  array<string, mixed>  $config  the node's config, as triggerReadConfig() left it
     * @param  array<string, mixed>|null  $state  what the last successful poll returned; null on the first
     * @return array{items: list<array<string, mixed>>, state: array<string, mixed>}
     */
    public function pollSince(Run $run, array $config, ?array $state): array;

    /**
     * Items shaped like the real ones, for "Fetch sample" in the editor, so a
     * flow can be built before anything has happened.
     *
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    public function triggerSampleItems(Run $run, array $config): array;
}
