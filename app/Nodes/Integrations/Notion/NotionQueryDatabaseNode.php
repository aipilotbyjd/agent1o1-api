<?php

namespace App\Nodes\Integrations\Notion;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class NotionQueryDatabaseNode extends AbstractNotionNode
{
    public function type(): string
    {
        return 'notion_query_database';
    }

    public function name(): string
    {
        return 'Notion: Query Database';
    }

    public function description(): string
    {
        return 'Lists the items of a database, with an optional filter and order.';
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
                'filter' => Field::json('Filter (JSON)', 'A Notion filter, e.g. {"property": "Status", "status": {"equals": "Done"}}. Leave empty for every item.'),
                'sorts' => Field::json('Sorts (JSON)', 'A list, e.g. [{"property": "Due", "direction": "ascending"}]. Leave empty for the database order.', 'array'),
                'limit' => Field::integer('Limit', 'The most items to return (up to 500).', 100, 1, 500),
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
            $this->jsonField($config, 'sorts', 'Sorts'),
            max(1, min(500, (int) ($config['limit'] ?? 100))),
        );

        return ['items' => $items, 'count' => count($items)];
    }
}
