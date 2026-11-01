<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class AsanaListWorkspacesNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_list_workspaces';
    }

    public function name(): string
    {
        return 'Asana: List Workspaces';
    }

    public function description(): string
    {
        return 'Lists the workspaces and organisations the user belongs to.';
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
        $workspaces = $this->listAll($run, $config, '/workspaces', ['opt_fields' => 'name,is_organization'], 100);

        return ['workspaces' => array_map(fn (array $workspace): array => ['id' => $workspace['gid'] ?? null, 'name' => $workspace['name'] ?? null, 'organization' => (bool) ($workspace['is_organization'] ?? false)], $workspaces), 'count' => count($workspaces)];
    }
}
