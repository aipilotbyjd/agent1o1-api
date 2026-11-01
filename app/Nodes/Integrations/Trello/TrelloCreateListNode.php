<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TrelloCreateListNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_create_list';
    }

    public function name(): string
    {
        return 'Trello: Create List';
    }

    public function description(): string
    {
        return 'Adds a list (column) to a board.';
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
                'name' => Field::text('Name', null, 'In review'),
                'position' => Field::advanced(Field::text('Position', 'top, bottom, or a number. Empty is the bottom.', 'top')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $list = $this->trello($run, $config, 'POST', '/lists', ['name' => $this->requiredString($config, 'name', 'The name'), 'idBoard' => $this->id($config, 'board_id', 'The board'), 'pos' => $this->position($config)]);

        return ['id' => $list['id'] ?? null, 'name' => $list['name'] ?? null, 'board_id' => $list['idBoard'] ?? null];
    }
}
