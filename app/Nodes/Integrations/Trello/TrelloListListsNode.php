<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TrelloListListsNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_list_lists';
    }

    public function name(): string
    {
        return 'Trello: List Lists on a Board';
    }

    public function description(): string
    {
        return 'Lists the lists (columns) of a board in order.';
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
                'include_archived' => Field::advanced(Field::boolean('Include archived lists', null, false)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $lists = $this->trello($run, $config, 'GET', '/boards/'.$this->id($config, 'board_id', 'The board').'/lists', ['filter' => ($config['include_archived'] ?? false) ? 'all' : 'open', 'fields' => 'id,name,closed,pos']);

        return ['lists' => array_map(fn (array $list): array => ['id' => $list['id'] ?? null, 'name' => $list['name'] ?? null, 'archived' => (bool) ($list['closed'] ?? false), 'position' => $list['pos'] ?? null], array_values(array_filter($lists, 'is_array'))), 'count' => count($lists)];
    }
}
