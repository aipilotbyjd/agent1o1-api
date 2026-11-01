<?php

namespace App\Nodes\Integrations\Pipedrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class PipedriveListUsersNode extends AbstractPipedriveNode
{
    public function type(): string
    {
        return 'pipedrive_list_users';
    }

    public function name(): string
    {
        return 'Pipedrive: List Users';
    }

    public function description(): string
    {
        return 'Lists the people in the Pipedrive account, to assign records to.';
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
        $found = (array) ($this->pipedrive($run, $config, 'GET', '/users')['data'] ?? []);
        $users = array_values(array_filter($found, 'is_array'));

        return ['users' => array_map(fn (array $user): array => ['id' => $user['id'] ?? null, 'name' => $user['name'] ?? null, 'email' => $user['email'] ?? null, 'active' => (bool) ($user['active_flag'] ?? false)], $users), 'count' => count($users)];
    }
}
