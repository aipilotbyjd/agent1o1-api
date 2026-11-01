<?php

namespace App\Nodes\Integrations\Monday;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MondayUpdateItemNode extends AbstractMondayNode
{
    public function type(): string
    {
        return 'monday_update_item';
    }

    public function name(): string
    {
        return 'monday.com: Update Item Columns';
    }

    public function description(): string
    {
        return 'Sets several column values of an item at once. Columns you leave out keep their values.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['board_id', 'item_id', 'column_values'],
            'properties' => [
                ...$this->credentialFields(),
                'board_id' => $this->boardField(),
                'item_id' => Field::text('Item', 'The item id, from Create Item or List Items.', '1234567890'),
                'column_values' => Field::json('Column values (JSON)', 'By column id, e.g. {"status": {"label": "Done"}, "numbers": 42}.'),
                'create_labels' => Field::advanced(Field::boolean('Create missing labels', 'Add a status or dropdown label that does not exist yet.', false)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $item = (array) ($this->monday($run, $config, 'mutation ($board: ID!, $item: ID!, $values: JSON!, $labels: Boolean) { change_multiple_column_values(board_id: $board, item_id: $item, column_values: $values, create_labels_if_missing: $labels) { '.self::ITEM_FIELDS.' } }', [
            'board' => $this->numericId($config, 'board_id', 'The board'),
            'item' => $this->numericId($config, 'item_id', 'The item'),
            'values' => $this->columnValues($config),
            'labels' => (bool) ($config['create_labels'] ?? false),
        ])['change_multiple_column_values'] ?? []);

        return $this->shapeItem($item);
    }
}
