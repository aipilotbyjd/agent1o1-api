<?php

namespace App\Nodes\Integrations\Monday;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use RuntimeException;

class MondayGetBoardNode extends AbstractMondayNode
{
    public function type(): string
    {
        return 'monday_get_board';
    }

    public function name(): string
    {
        return 'monday.com: Get Board';
    }

    public function description(): string
    {
        return 'Reads a board with its groups and columns (the ids to use when writing values).';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $found = $this->monday($run, $config, 'query ($ids: [ID!]) { boards(ids: $ids) { id name description board_kind state groups { id title color } columns { id title type } } }', ['ids' => [$this->numericId($config, 'board_id', 'The board')]]);
        $board = (array) (($found['boards'] ?? [])[0] ?? throw new RuntimeException('monday.com error: that board was not found, or the token cannot see it.'));

        return [
            'id' => isset($board['id']) ? (string) $board['id'] : null,
            'name' => $board['name'] ?? null,
            'description' => $board['description'] ?? null,
            'kind' => $board['board_kind'] ?? null,
            'groups' => array_values(array_map(fn (array $group): array => ['id' => $group['id'] ?? null, 'title' => $group['title'] ?? null], array_filter((array) ($board['groups'] ?? []), 'is_array'))),
            'columns' => array_values(array_map(fn (array $column): array => ['id' => $column['id'] ?? null, 'title' => $column['title'] ?? null, 'type' => $column['type'] ?? null], array_filter((array) ($board['columns'] ?? []), 'is_array'))),
        ];
    }
}
