<?php

namespace App\Nodes\Integrations\Monday;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MondayCreateGroupNode extends AbstractMondayNode
{
    public function type(): string
    {
        return 'monday_create_group';
    }

    public function name(): string
    {
        return 'monday.com: Create Group';
    }

    public function description(): string
    {
        return 'Adds a group (a section of items) to a board.';
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
                'name' => Field::text('Name', null, 'This week'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $group = (array) ($this->monday($run, $config, 'mutation ($board: ID!, $name: String!) { create_group(board_id: $board, group_name: $name) { id title } }', ['board' => $this->numericId($config, 'board_id', 'The board'), 'name' => $this->requiredString($config, 'name', 'The name')])['create_group'] ?? []);

        return ['id' => $group['id'] ?? null, 'title' => $group['title'] ?? null];
    }
}
