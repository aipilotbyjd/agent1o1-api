<?php

namespace App\Nodes\Integrations\Monday;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MondayListBoardsNode extends AbstractMondayNode
{
    public function type(): string
    {
        return 'monday_list_boards';
    }

    public function name(): string
    {
        return 'monday.com: List Boards';
    }

    public function description(): string
    {
        return 'Lists the boards, most recently used first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'limit' => Field::integer('Max results', null, 50, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $boards = array_values(array_filter((array) ($this->monday($run, $config, 'query ($limit: Int) { boards(limit: $limit, state: active, order_by: used_at) { id name description board_kind workspace { id name } } }', ['limit' => $this->limit($config, 50, 500)])['boards'] ?? []), 'is_array'));

        return ['boards' => array_map(fn (array $board): array => ['id' => isset($board['id']) ? (string) $board['id'] : null, 'name' => $board['name'] ?? null, 'description' => $board['description'] ?? null, 'kind' => $board['board_kind'] ?? null, 'workspace' => data_get($board, 'workspace.name')], $boards), 'count' => count($boards)];
    }
}
