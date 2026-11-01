<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class AsanaListSectionsNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_list_sections';
    }

    public function name(): string
    {
        return 'Asana: List Sections';
    }

    public function description(): string
    {
        return 'Lists the sections (columns) of a project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id'],
            'properties' => [
                ...$this->credentialFields(),
                'workspace_id' => $this->workspaceField(),
                'project_id' => $this->projectField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $sections = $this->listAll($run, $config, '/projects/'.$this->gid($config, 'project_id', 'The project').'/sections', ['opt_fields' => 'name'], 100);

        return ['sections' => array_map(fn (array $section): array => ['id' => $section['gid'] ?? null, 'name' => $section['name'] ?? null], $sections), 'count' => count($sections)];
    }
}
