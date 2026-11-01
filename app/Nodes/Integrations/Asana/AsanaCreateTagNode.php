<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AsanaCreateTagNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_create_tag';
    }

    public function name(): string
    {
        return 'Asana: Create Tag';
    }

    public function description(): string
    {
        return 'Creates a tag in a workspace.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'workspace_id' => $this->workspaceField(),
                'name' => Field::text('Name', null, 'Urgent'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $tag = (array) $this->asana($run, $config, 'POST', '/tags', [], ['name' => $this->requiredString($config, 'name', 'The name'), 'workspace' => $this->workspace($run, $config)])['data'];

        return ['id' => $tag['gid'] ?? null, 'name' => $tag['name'] ?? null];
    }
}
