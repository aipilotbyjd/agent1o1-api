<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TrelloRemoveLabelNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_remove_label';
    }

    public function name(): string
    {
        return 'Trello: Remove Label from Card';
    }

    public function description(): string
    {
        return 'Takes a label off a card.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['card_id', 'label_id'],
            'properties' => [
                ...$this->credentialFields(),
                'card_id' => Field::text('Card', 'The card id, from Create Card or List Cards.', '5f1c2b3a4d5e6f7a8b9c0d1e'),
                'board_id' => $this->boardField(),
                'label_id' => Field::dynamic('Label', 'trello.labels', null, 'Urgent', dependsOn: ['board_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->id($config, 'card_id', 'The card');
        $label = $this->id($config, 'label_id', 'The label');

        $this->trello($run, $config, 'DELETE', "/cards/{$id}/idLabels/{$label}");

        return ['removed' => true, 'card_id' => $id, 'label_id' => $label];
    }
}
