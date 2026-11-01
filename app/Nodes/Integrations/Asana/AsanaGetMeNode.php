<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class AsanaGetMeNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_get_me';
    }

    public function name(): string
    {
        return 'Asana: Get Me';
    }

    public function description(): string
    {
        return 'Reads the connected Asana user and their workspaces. Checks the connection works.';
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
        $me = (array) $this->asana($run, $config, 'GET', '/users/me', ['opt_fields' => 'name,email,workspaces.name'])['data'];

        return ['id' => $me['gid'] ?? null, 'name' => $me['name'] ?? null, 'email' => $me['email'] ?? null, 'workspaces' => array_values(array_map(fn (array $workspace): array => ['id' => $workspace['gid'] ?? null, 'name' => $workspace['name'] ?? null], array_filter((array) ($me['workspaces'] ?? []), 'is_array')))];
    }
}
