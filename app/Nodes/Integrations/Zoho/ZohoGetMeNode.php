<?php

namespace App\Nodes\Integrations\Zoho;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ZohoGetMeNode extends AbstractZohoNode
{
    public function type(): string
    {
        return 'zoho_get_me';
    }

    public function name(): string
    {
        return 'Zoho CRM: Get Me';
    }

    public function description(): string
    {
        return 'Reads the connected Zoho CRM user. Checks the connection works.';
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
        $user = (array) ((($this->zoho($run, $config, 'GET', '/users', ['type' => 'CurrentUser'])['users'] ?? [])[0]) ?? []);

        return ['id' => isset($user['id']) ? (string) $user['id'] : null, 'name' => $user['full_name'] ?? null, 'email' => $user['email'] ?? null, 'role' => data_get($user, 'role.name'), 'profile' => data_get($user, 'profile.name')];
    }
}
