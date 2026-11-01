<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TrelloAddCommentNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_add_comment';
    }

    public function name(): string
    {
        return 'Trello: Add Comment';
    }

    public function description(): string
    {
        return 'Comments on a card.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['card_id', 'text'],
            'properties' => [
                ...$this->credentialFields(),
                'card_id' => Field::text('Card', 'The card id, from Create Card or List Cards.', '5f1c2b3a4d5e6f7a8b9c0d1e'),
                'text' => Field::textarea('Comment'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->id($config, 'card_id', 'The card');
        $comment = $this->trello($run, $config, 'POST', "/cards/{$id}/actions/comments", [], ['text' => $this->requiredString($config, 'text', 'The comment')]);

        return ['id' => $comment['id'] ?? null, 'card_id' => $id, 'text' => data_get($comment, 'data.text'), 'date' => $comment['date'] ?? null];
    }
}
