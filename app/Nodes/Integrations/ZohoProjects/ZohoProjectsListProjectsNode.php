<?php

namespace App\Nodes\Integrations\ZohoProjects;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoProjectsListProjectsNode extends AbstractZohoProjectsNode
{
    public function type(): string
    {
        return 'zoho_projects_list_projects';
    }

    public function name(): string
    {
        return 'Zoho Projects: List Projects';
    }

    public function description(): string
    {
        return 'Lists the projects of the portal.';
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
                'status' => Field::advanced(Field::select('Status', ['active' => 'Active', 'archived' => 'Archived', 'template' => 'Template'], null, 'active')),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $projects = $this->listAll($run, $config, '/projects', 'projects', ['status' => in_array($config['status'] ?? null, ['active', 'archived', 'template'], true) ? $config['status'] : 'active'], $this->limit($config, 50));

        return ['projects' => array_map($this->shapeProject(...), $projects), 'count' => count($projects)];
    }
}
