<?php

namespace App\Nodes\Integrations\Monday;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MondaySetColumnNode extends AbstractMondayNode
{
    public function type(): string
    {
        return 'monday_set_column';
    }

    public function name(): string
    {
        return 'monday.com: Set Column Value';
    }

    public function description(): string
    {
        return 'Sets one column of an item from plain text: a status label, a number, text, a date (2026-03-31).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['board_id', 'item_id', 'column_id', 'value'],
            'properties' => [
                ...$this->credentialFields(),
                'board_id' => $this->boardField(),
                'item_id' => Field::text('Item', 'The item id, from Create Item or List Items.', '1234567890'),
                'column_id' => Field::dynamic('Column', 'monday.columns', null, 'Status', dependsOn: ['board_id']),
                'value' => Field::text('Value'),
                'create_labels' => Field::advanced(Field::boolean('Create missing labels', 'Add a status or dropdown label that does not exist yet.', false)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $item = (array) ($this->monday($run, $config, 'mutation ($board: ID!, $item: ID!, $column: String!, $value: String!, $labels: Boolean) { change_simple_column_value(board_id: $board, item_id: $item, column_id: $column, value: $value, create_labels_if_missing: $labels) { id name } }', [
            'board' => $this->numericId($config, 'board_id', 'The board'),
            'item' => $this->numericId($config, 'item_id', 'The item'),
            'column' => $this->shortId($config, 'column_id', 'The column'),
            'value' => (string) ($config['value'] ?? ''),
            'labels' => (bool) ($config['create_labels'] ?? false),
        ])['change_simple_column_value'] ?? []);

        return ['id' => isset($item['id']) ? (string) $item['id'] : null, 'name' => $item['name'] ?? null, 'column_id' => trim((string) $config['column_id']), 'value' => (string) ($config['value'] ?? '')];
    }
}
