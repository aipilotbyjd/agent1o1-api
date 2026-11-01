<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AsanaListUsersNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_list_users';
    }

    public function name(): string
    {
        return 'Asana: List Users';
    }

    public function description(): string
    {
        return 'Lists the people in a workspace.';
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
                'workspace_id' => $this->workspaceField(),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $users = $this->listAll($run, $config, '/users', ['workspace' => $this->workspace($run, $config), 'opt_fields' => 'name,email'], $this->limit($config, 100));

        return ['users' => array_map(fn (array $user): array => ['id' => $user['gid'] ?? null, 'name' => $user['name'] ?? null, 'email' => $user['email'] ?? null], $users), 'count' => count($users)];
    }
}
