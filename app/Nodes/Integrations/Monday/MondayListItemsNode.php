<?php

namespace App\Nodes\Integrations\Monday;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MondayListItemsNode extends AbstractMondayNode
{
    public function type(): string
    {
        return 'monday_list_items';
    }

    public function name(): string
    {
        return 'monday.com: List Items';
    }

    public function description(): string
    {
        return 'Lists the items of a board, newest first, optionally only one group\'s. Each item\'s column values are keyed by column title and by id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['board_id'],
            'properties' => [
                ...$this->credentialFields(),
                'board_id' => $this->boardField(),
                'group_id' => Field::advanced($this->groupField('Only group')),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $group = filled($config['group_id'] ?? null) ? $this->shortId($config, 'group_id', 'The group') : null;
        $items = $this->readItems($run, $config, $this->numericId($config, 'board_id', 'The board'), $group, '__creation_log__', $this->limit($config, 50));

        return ['items' => array_map($this->shapeItem(...), $items), 'count' => count($items)];
    }
}
