<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class TrelloArchiveAllCardsNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_archive_all_cards';
    }

    public function name(): string
    {
        return 'Trello: Archive All Cards in List';
    }

    public function description(): string
    {
        return 'Archives every card in a list. Archived cards can be restored from the board menu.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id'],
            'properties' => [
                ...$this->credentialFields(),
                'board_id' => $this->boardField(),
                'list_id' => $this->listField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->id($config, 'list_id', 'The list');

        $this->trello($run, $config, 'POST', "/lists/{$id}/archiveAllCards");

        return ['archived' => true, 'list_id' => $id];
    }
}
