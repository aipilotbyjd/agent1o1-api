<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TrelloMoveCardNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_move_card';
    }

    public function name(): string
    {
        return 'Trello: Move Card';
    }

    public function description(): string
    {
        return 'Moves a card to another list, on the same board or another.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['card_id', 'list_id'],
            'properties' => [
                ...$this->credentialFields(),
                'card_id' => Field::text('Card', 'The card id, from Create Card or List Cards.', '5f1c2b3a4d5e6f7a8b9c0d1e'),
                'board_id' => $this->boardField('To board'),
                'list_id' => $this->listField('To list'),
                'position' => Field::advanced(Field::text('Position', 'top, bottom, or a number. Empty is the bottom.', 'top')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->id($config, 'card_id', 'The card');
        $card = $this->trello($run, $config, 'PUT', "/cards/{$id}", [], [
            'idList' => $this->id($config, 'list_id', 'The list'),
            'idBoard' => filled($config['board_id'] ?? null) ? $this->id($config, 'board_id', 'The board') : null,
            'pos' => $this->position($config),
        ]);

        return $this->shapeCard($card);
    }
}
