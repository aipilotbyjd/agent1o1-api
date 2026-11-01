<?php

namespace App\Nodes\Integrations\Typeform;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TypeformCreateWorkspaceNode extends AbstractTypeformNode
{
    public function type(): string
    {
        return 'typeform_create_workspace';
    }

    public function name(): string
    {
        return 'Typeform: Create Workspace';
    }

    public function description(): string
    {
        return 'Creates a workspace to keep forms in.';
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
                'name' => Field::text('Name', null, 'Marketing'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $workspace = (array) $this->typeform($run, $config, 'POST', '/workspaces', ['name' => $this->requiredString($config, 'name', 'The name')]);

        return ['id' => $workspace['id'] ?? null, 'name' => $workspace['name'] ?? null];
    }
}
