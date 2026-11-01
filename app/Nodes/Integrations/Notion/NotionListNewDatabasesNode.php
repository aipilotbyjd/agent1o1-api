<?php

namespace App\Nodes\Integrations\Notion;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class NotionListNewDatabasesNode extends AbstractNotionNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'notion_list_new_databases';
    }

    public function name(): string
    {
        return 'Notion: List New Databases';
    }

    public function description(): string
    {
        return 'Lists databases the connection can see that were made lately, newest first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::text('Only titles containing', 'Leave empty for every database the connection can see.'),
                'limit' => Field::advanced(Field::integer('Limit', 'The most databases to read (up to 100).', 25, 1, 100)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['databases' => $this->searchObjects($run, $config, 'database', trim((string) ($config['query'] ?? '')), 'descending', max(1, min(100, (int) ($config['limit'] ?? 25))))];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'databases';
    }

    /**
     * Databases made in the last day (see NotionListNewPagesNode).
     */
    public function triggerItems(array $output, array $config): array
    {
        return $this->createdSince(array_values(array_filter((array) ($output['databases'] ?? []), 'is_array')), 86400);
    }

    /**
     * Read the widest page, so a burst between two polls isn't cut off.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 100];
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Notion: New database';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a database is created in what you shared with the connection.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 15;
    }

    /**
     * Newest first, so a poll sees the latest items.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
