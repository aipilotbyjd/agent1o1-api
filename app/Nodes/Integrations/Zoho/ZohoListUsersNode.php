<?php

namespace App\Nodes\Integrations\Zoho;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ZohoListUsersNode extends AbstractZohoNode
{
    public function type(): string
    {
        return 'zoho_list_users';
    }

    public function name(): string
    {
        return 'Zoho CRM: List Users';
    }

    public function description(): string
    {
        return 'Lists the active users, to assign records to.';
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
        $users = array_values(array_filter((array) ($this->zoho($run, $config, 'GET', '/users', ['type' => 'ActiveUsers'])['users'] ?? []), 'is_array'));

        return ['users' => array_map(fn (array $user): array => ['id' => isset($user['id']) ? (string) $user['id'] : null, 'name' => $user['full_name'] ?? null, 'email' => $user['email'] ?? null, 'role' => data_get($user, 'role.name')], $users), 'count' => count($users)];
    }
}
