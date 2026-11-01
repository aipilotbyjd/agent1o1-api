<?php

namespace App\Nodes\Integrations\Typeform;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TypeformDeleteWorkspaceNode extends AbstractTypeformNode
{
    public function type(): string
    {
        return 'typeform_delete_workspace';
    }

    public function name(): string
    {
        return 'Typeform: Delete Workspace';
    }

    public function description(): string
    {
        return 'Deletes an empty workspace.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workspace_id'],
            'properties' => [
                ...$this->credentialFields(),
                'workspace_id' => Field::dynamic('Workspace', 'typeform.workspaces', 'It must have no forms in it.', 'Marketing'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->requiredString($config, 'workspace_id', 'The workspace');

        $this->typeform($run, $config, 'DELETE', '/workspaces/'.rawurlencode($id));

        return ['deleted' => true, 'workspace_id' => $id];
    }
}
