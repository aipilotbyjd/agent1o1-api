<?php

namespace App\Nodes\Integrations\Typeform;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TypeformRenameWorkspaceNode extends AbstractTypeformNode
{
    public function type(): string
    {
        return 'typeform_rename_workspace';
    }

    public function name(): string
    {
        return 'Typeform: Rename Workspace';
    }

    public function description(): string
    {
        return 'Renames a workspace.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workspace_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'workspace_id' => Field::dynamic('Workspace', 'typeform.workspaces', null, 'Marketing'),
                'name' => Field::text('New name'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = rawurlencode($this->requiredString($config, 'workspace_id', 'The workspace'));
        $name = $this->requiredString($config, 'name', 'The new name');

        $this->typeform($run, $config, 'PATCH', "/workspaces/{$id}", [['op' => 'replace', 'path' => '/name', 'value' => $name]]);

        return ['id' => rawurldecode($id), 'name' => $name];
    }
}
