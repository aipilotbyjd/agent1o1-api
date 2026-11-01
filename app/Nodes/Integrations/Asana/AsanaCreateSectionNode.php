<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AsanaCreateSectionNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_create_section';
    }

    public function name(): string
    {
        return 'Asana: Create Section';
    }

    public function description(): string
    {
        return 'Adds a section (column) to a project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'workspace_id' => $this->workspaceField(),
                'project_id' => $this->projectField(),
                'name' => Field::text('Name', null, 'In review'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $section = (array) $this->asana($run, $config, 'POST', '/projects/'.$this->gid($config, 'project_id', 'The project').'/sections', [], ['name' => $this->requiredString($config, 'name', 'The name')])['data'];

        return ['id' => $section['gid'] ?? null, 'name' => $section['name'] ?? null];
    }
}
