<?php

namespace App\Nodes\Integrations\Concerns;

use App\Models\Runs\Run;
use App\Services\Triggers\PresenceTracker;

/**
 * Defaults for a SupportsTriggerMode node that also implements
 * KeepsTriggerState because its event is something going missing (see
 * PresenceTracker).
 *
 * Items are polled through pollSince(), so triggerItems() has nothing to pick
 * from a read. A removal is identified by what was removed and when it was
 * last seen, and passed on as it is.
 */
trait ActsAsRemovalTrigger
{
    /**
     * Not used: removals come from pollSince().
     */
    public function triggerItems(array $output, array $config): array
    {
        return [];
    }

    /**
     * The same removal always has the same id.
     */
    public function triggerItemId(array $item): string
    {
        return PresenceTracker::deliveryId($item);
    }

    /**
     * The removal as it is.
     */
    public function expandTriggerItem(Run $run, array $config, array $item): array
    {
        return $item;
    }

    /**
     * The node's own config.
     */
    public function triggerReadConfig(array $config): array
    {
        return $config;
    }

    /**
     * One at a time, in the order they were found.
     */
    public function triggerListsNewestFirst(): bool
    {
        return false;
    }

    /**
     * Every five minutes: a removal can't be told apart from a slow poll, so
     * there is no use polling faster than the source can answer.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }

    /**
     * What a removal looks like, for the sample in the editor.
     *
     * @return list<array<string, mixed>>
     */
    public function triggerSampleItems(Run $run, array $config): array
    {
        return [[
            'id' => 'example-id',
            'label' => 'Example item',
            'last_seen_at' => now()->subHour()->toIso8601String(),
            'removed_at' => now()->toIso8601String(),
        ]];
    }
}
