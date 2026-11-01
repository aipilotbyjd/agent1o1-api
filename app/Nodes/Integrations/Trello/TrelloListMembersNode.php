<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class TrelloListMembersNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_list_members';
    }

    public function name(): string
    {
        return 'Trello: List Board Members';
    }

    public function description(): string
    {
        return 'Lists the people on a board.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $members = $this->trello($run, $config, 'GET', '/boards/'.$this->id($config, 'board_id', 'The board').'/members', ['fields' => 'id,fullName,username']);

        return ['members' => array_map(fn (array $member): array => ['id' => $member['id'] ?? null, 'name' => $member['fullName'] ?? null, 'username' => $member['username'] ?? null], array_values(array_filter($members, 'is_array'))), 'count' => count($members)];
    }
}
