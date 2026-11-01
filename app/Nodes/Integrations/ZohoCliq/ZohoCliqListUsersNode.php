<?php

namespace App\Nodes\Integrations\ZohoCliq;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoCliqListUsersNode extends AbstractZohoCliqNode
{
    public function type(): string
    {
        return 'zoho_cliq_list_users';
    }

    public function name(): string
    {
        return 'Zoho Cliq: List Users';
    }

    public function description(): string
    {
        return 'Lists the people in the organization.';
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
                'limit' => Field::integer('Max results', null, 50, 1, 200),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $users = (array) ($this->cliq($run, $config, 'GET', '/users', ['limit' => $this->limit($config, 50)])['users'] ?? []);

        return ['users' => array_map(fn (array $user): array => ['id' => isset($user['id']) ? (string) $user['id'] : null, 'name' => $user['name'] ?? trim(($user['first_name'] ?? '').' '.($user['last_name'] ?? '')) ?: null, 'email' => $user['email_id'] ?? $user['email'] ?? null, 'status' => $user['status'] ?? null], array_values(array_filter($users, 'is_array'))), 'count' => count($users)];
    }
}
