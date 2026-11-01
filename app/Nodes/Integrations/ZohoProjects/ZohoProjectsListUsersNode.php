<?php

namespace App\Nodes\Integrations\ZohoProjects;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ZohoProjectsListUsersNode extends AbstractZohoProjectsNode
{
    public function type(): string
    {
        return 'zoho_projects_list_users';
    }

    public function name(): string
    {
        return 'Zoho Projects: List Users';
    }

    public function description(): string
    {
        return 'Lists the people in the portal, and checks the connection works.';
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
        $users = $this->listAll($run, $config, '/users', 'users', [], 200);

        return ['users' => array_map(fn (array $user): array => ['id' => isset($user['id']) ? (string) $user['id'] : null, 'name' => $user['name'] ?? null, 'email' => $user['email'] ?? null, 'role' => data_get($user, 'role.name') ?? ($user['role'] ?? null), 'active' => ($user['status'] ?? 'active') !== 'inactive'], $users), 'count' => count($users)];
    }
}
