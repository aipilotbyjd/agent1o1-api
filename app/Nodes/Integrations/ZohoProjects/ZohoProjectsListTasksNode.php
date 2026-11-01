<?php

namespace App\Nodes\Integrations\ZohoProjects;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoProjectsListTasksNode extends AbstractZohoProjectsNode
{
    public function type(): string
    {
        return 'zoho_projects_list_tasks';
    }

    public function name(): string
    {
        return 'Zoho Projects: List Tasks';
    }

    public function description(): string
    {
        return 'Lists tasks of one project, or of the whole portal, newest first.';
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
                'status' => Field::advanced(Field::select('Status', ['notcompleted' => 'Not completed', 'completed' => 'Completed', 'all' => 'All'], null, 'notcompleted')),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $project = $this->optionalId($config, 'project_id', 'The project');
        $tasks = $this->listAll($run, $config, $project === null ? '/tasks' : "/projects/{$project}/tasks", 'tasks', [
            'status' => match ($config['status'] ?? 'notcompleted') {
                'completed' => 'completed',
                'all' => null,
                default => 'notcompleted',
            },
            'sort_by' => 'DESC(created_time)',
        ], $this->limit($config, 50));

        return ['tasks' => array_map($this->shapeTask(...), $tasks), 'count' => count($tasks)];
    }
}
