<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class TrelloGetMemberNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_get_member';
    }

    public function name(): string
    {
        return 'Trello: Get Me';
    }

    public function description(): string
    {
        return 'Reads the connected Trello member: their name and username. Checks the connection works.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $me = $this->trello($run, $config, 'GET', '/members/me', ['fields' => 'id,fullName,username,email']);

        return ['id' => $me['id'] ?? null, 'name' => $me['fullName'] ?? null, 'username' => $me['username'] ?? null, 'email' => $me['email'] ?? null];
    }
}
