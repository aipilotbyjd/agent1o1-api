<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ActiveCampaignGetAccountNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_get_account';
    }

    public function name(): string
    {
        return 'ActiveCampaign: Get Current User';
    }

    public function description(): string
    {
        return 'Gets the user the API key belongs to.';
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
        $user = (array) data_get((array) $this->ac($run, $config, 'GET', '/users/me'), 'user', []);

        return ['id' => isset($user['id']) ? (int) $user['id'] : null, 'email' => $user['email'] ?? null, 'first_name' => $user['firstName'] ?? null, 'last_name' => $user['lastName'] ?? null, 'username' => $user['username'] ?? null];
    }
}
