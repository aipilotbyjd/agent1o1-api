<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class TrelloGetBoardNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_get_board';
    }

    public function name(): string
    {
        return 'Trello: Get Board';
    }

    public function description(): string
    {
        return 'Reads a board with its lists.';
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
        $id = $this->id($config, 'board_id', 'The board');
        $board = $this->trello($run, $config, 'GET', "/boards/{$id}", ['fields' => 'id,name,desc,url,closed', 'lists' => 'open', 'list_fields' => 'id,name']);

        return ['id' => $board['id'] ?? $id, 'name' => $board['name'] ?? null, 'description' => $board['desc'] ?? null, 'url' => $board['url'] ?? null, 'closed' => (bool) ($board['closed'] ?? false), 'lists' => array_values(array_map(fn (array $list): array => ['id' => $list['id'] ?? null, 'name' => $list['name'] ?? null], array_filter((array) ($board['lists'] ?? []), 'is_array')))];
    }
}
