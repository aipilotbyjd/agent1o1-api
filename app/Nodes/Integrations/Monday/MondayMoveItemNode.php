<?php

namespace App\Nodes\Integrations\Monday;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MondayMoveItemNode extends AbstractMondayNode
{
    public function type(): string
    {
        return 'monday_move_item';
    }

    public function name(): string
    {
        return 'monday.com: Move Item to Group';
    }

    public function description(): string
    {
        return 'Moves an item to another group of its board.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['item_id', 'group_id'],
            'properties' => [
                ...$this->credentialFields(),
                'board_id' => $this->boardField(),
                'item_id' => Field::text('Item', 'The item id, from Create Item or List Items.', '1234567890'),
                'group_id' => $this->groupField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $item = (array) ($this->monday($run, $config, 'mutation ($item: ID!, $group: String!) { move_item_to_group(item_id: $item, group_id: $group) { id } }', ['item' => $this->numericId($config, 'item_id', 'The item'), 'group' => $this->shortId($config, 'group_id', 'The group')])['move_item_to_group'] ?? []);

        return ['moved' => true, 'item_id' => isset($item['id']) ? (string) $item['id'] : trim((string) $config['item_id']), 'group_id' => trim((string) $config['group_id'])];
    }
}
