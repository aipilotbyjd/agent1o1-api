<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TrelloListCardsNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_list_cards';
    }

    public function name(): string
    {
        return 'Trello: List Cards';
    }

    public function description(): string
    {
        return 'Lists the cards in a list, or on a whole board.';
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
                'board_id' => $this->boardField(),
                'list_id' => $this->listField(),
                'status' => Field::advanced(Field::select('Show', ['open' => 'Open cards', 'closed' => 'Archived cards', 'all' => 'All'], null, 'open')),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $status = in_array($config['status'] ?? 'open', ['open', 'closed', 'all'], true) ? ($config['status'] ?? 'open') : 'open';
        $path = filled($config['list_id'] ?? null) ? '/lists/'.$this->id($config, 'list_id', 'The list').'/cards' : '/boards/'.$this->id($config, 'board_id', 'The board or the list').'/cards';
        $cards = array_slice(array_values(array_filter($this->trello($run, $config, 'GET', $path, ['filter' => $status, 'fields' => 'id,name,desc,shortUrl,idList,idBoard,due,dueComplete,closed,idLabels,idMembers,dateLastActivity', 'limit' => $this->limit($config, 50)]), 'is_array')), 0, $this->limit($config, 50));

        return ['cards' => array_map($this->shapeCard(...), $cards), 'count' => count($cards)];
    }
}
