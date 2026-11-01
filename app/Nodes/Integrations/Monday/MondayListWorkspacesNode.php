<?php

namespace App\Nodes\Integrations\Monday;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MondayListWorkspacesNode extends AbstractMondayNode
{
    public function type(): string
    {
        return 'monday_list_workspaces';
    }

    public function name(): string
    {
        return 'monday.com: List Workspaces';
    }

    public function description(): string
    {
        return 'Lists the workspaces the user can see.';
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
        $workspaces = array_values(array_filter((array) ($this->monday($run, $config, '{ workspaces(limit: 100) { id name kind } }')['workspaces'] ?? []), 'is_array'));

        return ['workspaces' => array_map(fn (array $workspace): array => ['id' => isset($workspace['id']) ? (string) $workspace['id'] : null, 'name' => $workspace['name'] ?? null, 'kind' => $workspace['kind'] ?? null], $workspaces), 'count' => count($workspaces)];
    }
}
