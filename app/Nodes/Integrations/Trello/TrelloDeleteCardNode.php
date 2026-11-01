<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TrelloDeleteCardNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_delete_card';
    }

    public function name(): string
    {
        return 'Trello: Delete Card';
    }

    public function description(): string
    {
        return 'Deletes a card for good. Archiving keeps it; this does not.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['card_id'],
            'properties' => [
                ...$this->credentialFields(),
                'card_id' => Field::text('Card', 'The card id, from Create Card or List Cards.', '5f1c2b3a4d5e6f7a8b9c0d1e'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->id($config, 'card_id', 'The card');

        $this->trello($run, $config, 'DELETE', "/cards/{$id}");

        return ['deleted' => true, 'card_id' => $id];
    }
}
