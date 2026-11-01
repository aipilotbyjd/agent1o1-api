<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TrelloSearchCardsNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_search_cards';
    }

    public function name(): string
    {
        return 'Trello: Search Cards';
    }

    public function description(): string
    {
        return 'Finds cards by words in their title, description or comments, optionally on certain boards.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::text('Search', 'Words to find. Trello also takes operators like @ada, #label, list:"Done" and is:open.', 'launch'),
                'board_ids' => Field::advanced(Field::dynamic('Only on boards', 'trello.boards', null, 'Marketing', multiple: true, type: 'array')),
                'limit' => Field::integer('Max results', null, 25, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $boards = $this->ids($config['board_ids'] ?? [], 'A board');
        $found = $this->trello($run, $config, 'GET', '/search', [
            'query' => $this->requiredString($config, 'query', 'The search'),
            'modelTypes' => 'cards',
            'idBoards' => $boards === [] ? 'mine' : implode(',', $boards),
            'cards_limit' => $this->limit($config, 25),
            'card_fields' => 'id,name,desc,shortUrl,idList,idBoard,due,dueComplete,closed,idLabels,idMembers,dateLastActivity',
        ]);
        $cards = array_values(array_filter((array) ($found['cards'] ?? []), 'is_array'));

        return ['cards' => array_map($this->shapeCard(...), $cards), 'count' => count($cards)];
    }
}
