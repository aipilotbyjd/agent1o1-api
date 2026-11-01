<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class AsanaGetProjectNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_get_project';
    }

    public function name(): string
    {
        return 'Asana: Get Project';
    }

    public function description(): string
    {
        return 'Reads a project with its sections.';
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
        $id = $this->gid($config, 'project_id', 'The project');
        $project = (array) $this->asana($run, $config, 'GET', "/projects/{$id}", ['opt_fields' => 'name,notes,archived,color,permalink_url,owner.name,due_on,current_status.title'])['data'];
        $sections = $this->listAll($run, $config, "/projects/{$id}/sections", ['opt_fields' => 'name'], 100);

        return ['id' => $project['gid'] ?? $id, 'name' => $project['name'] ?? null, 'notes' => $project['notes'] ?? null, 'archived' => (bool) ($project['archived'] ?? false), 'url' => $project['permalink_url'] ?? null, 'owner' => data_get($project, 'owner.name'), 'due_on' => $project['due_on'] ?? null, 'status' => data_get($project, 'current_status.title'), 'sections' => array_map(fn (array $section): array => ['id' => $section['gid'] ?? null, 'name' => $section['name'] ?? null], $sections)];
    }
}
