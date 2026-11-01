<?php

namespace App\Nodes\Integrations\ClickUp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ClickUpListSpacesNode extends AbstractClickUpNode
{
    public function type(): string
    {
        return 'clickup_list_spaces';
    }

    public function name(): string
    {
        return 'ClickUp: List Spaces';
    }

    public function description(): string
    {
        return 'Lists the spaces of a workspace.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workspace_id'],
            'properties' => [
                ...$this->credentialFields(),
                'workspace_id' => $this->workspaceField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $spaces = array_values(array_filter((array) ($this->clickup($run, $config, 'GET', '/team/'.$this->numericId($config, 'workspace_id', 'The workspace').'/space', ['archived' => false])['spaces'] ?? []), 'is_array'));

        return ['spaces' => array_map(fn (array $space): array => ['id' => $space['id'] ?? null, 'name' => $space['name'] ?? null, 'private' => (bool) ($space['private'] ?? false)], $spaces), 'count' => count($spaces)];
    }
}
