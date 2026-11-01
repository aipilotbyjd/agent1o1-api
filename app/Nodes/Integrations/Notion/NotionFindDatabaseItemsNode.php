<?php

namespace App\Nodes\Integrations\Notion;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class NotionFindDatabaseItemsNode extends AbstractNotionNode
{
    public function type(): string
    {
        return 'notion_find_database_items';
    }

    public function name(): string
    {
        return 'Notion: Find Database Items';
    }

    public function description(): string
    {
        return 'Finds the items of a database whose column has a value.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['database', 'column', 'value'],
            'properties' => [
                ...$this->credentialFields(),
                'database' => Field::dynamic('Database', 'notion.databases', 'Pick a database, or paste its link or id.'),
                'column' => Field::dynamic('Column', 'notion.properties', 'The column to look in.', dependsOn: ['database']),
                'value' => Field::text('Value', 'Text, a number, a choice name, a date, or true/false for a checkbox.'),
                'match' => Field::select('Match', ['equals' => 'Equals', 'contains' => 'Contains'], 'Contains works for text and for choices in a multi-select.', 'equals'),
                'limit' => Field::integer('Limit', 'The most items to return (up to 100).', 25, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $databaseId = $this->id($config, 'database', 'The database');
        $column = (string) $config['column'];
        $type = (string) ($this->get($run, "/databases/{$databaseId}", $config)['properties'][$column]['type'] ?? '');
        $value = (string) $config['value'];
        $operator = ($config['match'] ?? 'equals') === 'contains' ? 'contains' : 'equals';

        $condition = match ($type) {
            'title', 'rich_text', 'url', 'email', 'phone_number' => [$type => [$operator => $value]],
            'select', 'status' => [$type => ['equals' => $value]],
            'multi_select' => ['multi_select' => ['contains' => $value]],
            'number' => ['number' => ['equals' => (float) $value + 0]],
            'checkbox' => ['checkbox' => ['equals' => filter_var($value, FILTER_VALIDATE_BOOLEAN)]],
            'date' => ['date' => ['equals' => $value]],
            default => throw new \InvalidArgumentException($type === '' ? "Notion: the database has no column named \"{$column}\"." : "Notion: a {$type} column can't be searched here. Use Query Database with a filter."),
        };

        $items = $this->queryDatabase($run, $databaseId, $config, ['property' => $column, ...$condition], [], max(1, min(100, (int) ($config['limit'] ?? 25))));

        return ['items' => $items, 'count' => count($items), 'first' => $items[0] ?? null];
    }
}
