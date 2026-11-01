<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TrelloRemoveMemberNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_remove_member';
    }

    public function name(): string
    {
        return 'Trello: Unassign Member from Card';
    }

    public function description(): string
    {
        return 'Takes a person off a card.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['card_id', 'member_id'],
            'properties' => [
                ...$this->credentialFields(),
                'card_id' => Field::text('Card', 'The card id, from Create Card or List Cards.', '5f1c2b3a4d5e6f7a8b9c0d1e'),
                'board_id' => $this->boardField(),
                'member_id' => Field::dynamic('Member', 'trello.members', null, 'Ada', dependsOn: ['board_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->id($config, 'card_id', 'The card');
        $member = $this->id($config, 'member_id', 'The member');

        $this->trello($run, $config, 'DELETE', "/cards/{$id}/idMembers/{$member}");

        return ['removed' => true, 'card_id' => $id, 'member_id' => $member];
    }
}
