<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AsanaListProjectsNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_list_projects';
    }

    public function name(): string
    {
        return 'Asana: List Projects';
    }

    public function description(): string
    {
        return 'Lists the projects of a workspace.';
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
                'workspace_id' => $this->workspaceField(),
                'include_archived' => Field::advanced(Field::boolean('Include archived projects', null, false)),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $projects = $this->listAll($run, $config, '/projects', ['workspace' => $this->workspace($run, $config), 'archived' => (bool) ($config['include_archived'] ?? false) ? null : false, 'opt_fields' => 'name,notes,archived,color,permalink_url,owner.name,due_on'], $this->limit($config, 50));

        return ['projects' => array_map(fn (array $project): array => ['id' => $project['gid'] ?? null, 'name' => $project['name'] ?? null, 'notes' => $project['notes'] ?? null, 'archived' => (bool) ($project['archived'] ?? false), 'color' => $project['color'] ?? null, 'url' => $project['permalink_url'] ?? null, 'owner' => data_get($project, 'owner.name'), 'due_on' => $project['due_on'] ?? null], $projects), 'count' => count($projects)];
    }
}
