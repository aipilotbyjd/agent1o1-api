<?php

namespace App\Nodes\Integrations\Notion;

use App\Contracts\KeepsTriggerState;
use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsRemovalTrigger;
use App\Nodes\Support\Field;
use App\Services\Triggers\PresenceTracker;

/**
 * Reads the items of a database. As a trigger it fires when an item is
 * deleted.
 *
 * Notion's queries leave out items in the trash, so an item that was in the
 * database at one poll and isn't at the next was deleted, moved to another
 * database, or (with "Only items matching") stopped matching the filter. The
 * event says which it can't tell; the item's id, title and when it was last
 * seen are in the payload.
 */
class NotionListDeletedDatabaseItemsNode extends AbstractNotionNode implements KeepsTriggerState, SupportsTriggerMode
{
    use ActsAsRemovalTrigger;

    public function type(): string
    {
        return 'notion_list_deleted_database_items';
    }

    public function name(): string
    {
        return 'Notion: List Database Items';
    }

    public function description(): string
    {
        return 'Lists the items of a database. As a trigger it fires when an item is deleted, moved out, or stops matching the filter.';
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
                'filter' => Field::advanced(Field::json('Only items matching (JSON)', 'A Notion filter, e.g. {"property": "Status", "status": {"equals": "Done"}}. As a trigger, an item that stops matching counts as removed. Leave empty for every item.')),
                'limit' => Field::advanced(Field::integer('Limit', 'The most items to read (up to 100).', 25, 1, 100)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['items' => $this->queryDatabase(
            $run,
            $this->id($config, 'database', 'The database'),
            $config,
            $this->jsonField($config, 'filter', 'Filter'),
            [],
            max(1, min(100, (int) ($config['limit'] ?? 25))),
        )];
    }

    /**
     * The items that were in the database last poll and are gone now.
     */
    public function pollSince(Run $run, array $config, ?array $state): array
    {
        $limit = max(1, (int) config('triggers.removal.max_tracked'));

        $items = $this->queryDatabase(
            $run,
            $this->id($config, 'database', 'The database'),
            $config,
            $this->jsonField($config, 'filter', 'Filter'),
            [],
            $limit + 1,
        );

        $present = [];

        foreach ($items as $item) {
            if (isset($item['id']) && ! ($item['archived'] ?? false)) {
                $present[(string) $item['id']] = (string) ($item['title'] ?? '');
            }
        }

        $result = PresenceTracker::track($present, $state);
        $database = $this->id($config, 'database', 'The database');

        return [
            'items' => array_map(fn (array $item): array => [...$item, 'database_id' => $database], $result['items']),
            'state' => $result['state'],
        ];
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Notion: Database item deleted';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when an item is deleted from a Notion database (or moved out, or no longer matches the filter).';
    }

    /**
     * Notion is rate limited and an item is only noticed by reading the whole
     * database, so look less often than for new items.
     */
    public function triggerPollMinutes(): int
    {
        return 15;
    }
}
