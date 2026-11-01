<?php

namespace App\Nodes\Integrations\ZohoProjects;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoProjectsListBugsNode extends AbstractZohoProjectsNode
{
    public function type(): string
    {
        return 'zoho_projects_list_bugs';
    }

    public function name(): string
    {
        return 'Zoho Projects: List Bugs';
    }

    public function description(): string
    {
        return 'Lists the bugs (issues) of one project, or of the whole portal, newest first.';
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
                'project_id' => Field::advanced($this->projectField()),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $project = $this->optionalId($config, 'project_id', 'The project');
        $bugs = $this->listAll($run, $config, $project === null ? '/issues' : "/projects/{$project}/issues", 'issues', ['sort_by' => 'DESC(created_time)'], $this->limit($config, 50));

        return ['bugs' => array_map($this->shapeBug(...), $bugs), 'count' => count($bugs)];
    }
}
