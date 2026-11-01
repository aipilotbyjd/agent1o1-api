<?php

namespace App\Nodes\Integrations\Notion;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class NotionListUpdatedDatabaseItemsNode extends AbstractNotionNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'notion_list_updated_database_items';
    }

    public function name(): string
    {
        return 'Notion: List Updated Database Items';
    }

    public function description(): string
    {
        return 'Lists the most recently edited items of a database.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['database'],
            'properties' => [
                ...$this->credentialFields(),
                'database' => Field::dynamic('Database', 'notion.databases', 'Pick a database, or paste its link or id.'),
                'filter' => Field::advanced(Field::json('Only items matching (JSON)', 'A Notion filter, e.g. {"property": "Status", "status": {"equals": "Done"}}. Leave empty for every item.')),
                'limit' => Field::advanced(Field::integer('Limit', 'The most items to read (up to 100).', 25, 1, 100)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $items = $this->queryDatabase(
            $run,
            $this->id($config, 'database', 'The database'),
            $config,
            $this->jsonField($config, 'filter', 'Filter'),
            [['timestamp' => 'last_edited_time', 'direction' => 'descending']],
            max(1, min(100, (int) ($config['limit'] ?? 25))),
        );

        return ['items' => $items];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'items';
    }

    /**
     * An item is named by its id and the time of its last edit: each edit is a new event.
     */
    public function triggerItemId(array $item): string
    {
        return ($item['id'] ?? '').':'.($item['last_edited_time'] ?? '');
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
        return 'Notion: Database item updated';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when an item of a Notion database is added or changed.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }

    /**
     * Newest first, so a poll sees the latest items.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
