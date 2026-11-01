<?php

namespace App\Nodes\Integrations\Concerns;

use App\Models\Runs\Run;

/**
 * Defaults for SupportsTriggerMode.
 *
 * Items are read from triggerItemsPath(), identified by their `id`, passed on
 * as they are, and polled every five minutes. A node overrides whichever of
 * these doesn't fit.
 */
trait ActsAsTrigger
{
    /**
     * Where the item list sits in execute()'s output, or null when the output
     * is the list itself.
     */
    abstract protected function triggerItemsPath(): ?string;

    /**
     * The items under triggerItemsPath(), ignoring anything that isn't an
     * item.
     */
    public function triggerItems(array $output, array $config): array
    {
        $items = $this->triggerItemsPath() === null ? $output : data_get($output, $this->triggerItemsPath(), []);

        return array_values(array_filter((array) $items, 'is_array'));
    }

    /**
     * The item's `id`.
     */
    public function triggerItemId(array $item): string
    {
        return (string) ($item['id'] ?? '');
    }

    /**
     * The item as it is.
     */
    public function expandTriggerItem(Run $run, array $config, array $item): array
    {
        return $item;
    }

    /**
     * Every five minutes.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }

    /**
     * The node's own config.
     */
    public function triggerReadConfig(array $config): array
    {
        return $config;
    }

    /**
     * Oldest first.
     */
    public function triggerListsNewestFirst(): bool
    {
        return false;
    }
}
