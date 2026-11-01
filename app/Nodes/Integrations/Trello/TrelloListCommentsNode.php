<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TrelloListCommentsNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_list_comments';
    }

    public function name(): string
    {
        return 'Trello: List Comments';
    }

    public function description(): string
    {
        return 'Lists the comments on a card, newest first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['card_id'],
            'properties' => [
                ...$this->credentialFields(),
                'card_id' => Field::text('Card', 'The card id, from Create Card or List Cards.', '5f1c2b3a4d5e6f7a8b9c0d1e'),
                'limit' => Field::integer('Max results', null, 25, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $actions = $this->trello($run, $config, 'GET', '/cards/'.$this->id($config, 'card_id', 'The card').'/actions', ['filter' => 'commentCard', 'limit' => $this->limit($config, 25)]);
        $comments = array_map($this->shapeAction(...), array_values(array_filter($actions, 'is_array')));

        return ['comments' => $comments, 'count' => count($comments)];
    }
}
