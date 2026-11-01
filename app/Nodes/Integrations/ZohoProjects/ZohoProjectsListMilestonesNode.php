<?php

namespace App\Nodes\Integrations\ZohoProjects;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoProjectsListMilestonesNode extends AbstractZohoProjectsNode
{
    public function type(): string
    {
        return 'zoho_projects_list_milestones';
    }

    public function name(): string
    {
        return 'Zoho Projects: List Milestones';
    }

    public function description(): string
    {
        return 'Lists the milestones of a project.';
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
                'project_id' => $this->projectField(),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $project = $this->numericId($config, 'project_id', 'The project');
        $milestones = $this->listAll($run, $config, "/projects/{$project}/milestones", 'milestones', [], $this->limit($config, 50));

        return ['milestones' => array_map($this->shapeMilestone(...), $milestones), 'count' => count($milestones)];
    }
}
