<?php

namespace App\Nodes\Integrations\Monday;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MondayCreateItemNode extends AbstractMondayNode
{
    public function type(): string
    {
        return 'monday_create_item';
    }

    public function name(): string
    {
        return 'monday.com: Create Item';
    }

    public function description(): string
    {
        return 'Adds an item to a board and group, with column values.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['board_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'board_id' => $this->boardField(),
                'group_id' => $this->groupField(),
                'name' => Field::text('Item name', null, 'Write the launch email'),
                'column_values' => Field::json('Column values (JSON)', 'By column id, e.g. {"status": {"label": "Working on it"}, "date": {"date": "2026-03-31"}, "text": "Hello"}. Get Board lists the ids.'),
                'create_labels' => Field::advanced(Field::boolean('Create missing labels', 'Add a status or dropdown label that does not exist yet.', false)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $item = (array) ($this->monday($run, $config, 'mutation ($board: ID!, $group: String, $name: String, $values: JSON, $labels: Boolean) { create_item(board_id: $board, group_id: $group, item_name: $name, column_values: $values, create_labels_if_missing: $labels) { '.self::ITEM_FIELDS.' } }', [
            'board' => $this->numericId($config, 'board_id', 'The board'),
            'group' => filled($config['group_id'] ?? null) ? $this->shortId($config, 'group_id', 'The group') : null,
            'name' => $this->requiredString($config, 'name', 'The item name'),
            'values' => $this->columnValues($config, required: false),
            'labels' => (bool) ($config['create_labels'] ?? false),
        ])['create_item'] ?? []);

        return $this->shapeItem($item);
    }
}
