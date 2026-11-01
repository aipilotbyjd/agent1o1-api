<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ActiveCampaignListUsersNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_list_users';
    }

    public function name(): string
    {
        return 'ActiveCampaign: List Users';
    }

    public function description(): string
    {
        return 'Lists the account\'s users (deal owners).';
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
        $users = $this->listAll($run, $config, '/users', 'users', [], 1000);

        return ['users' => array_map(fn (array $user): array => ['id' => (int) $user['id'], 'email' => $user['email'] ?? null, 'first_name' => $user['firstName'] ?? null, 'last_name' => $user['lastName'] ?? null], $users), 'count' => count($users)];
    }
}
