<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AsanaSearchTasksNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_search_tasks';
    }

    public function name(): string
    {
        return 'Asana: Search Tasks';
    }

    public function description(): string
    {
        return 'Finds tasks in a workspace by words, assignee, project or status. Asana only offers search on paid plans.';
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
                'text' => Field::text('Words', 'Looked for in the name and description.', 'launch'),
                'assignee' => Field::advanced(Field::text('Assigned to', 'me, or a user id.')),
                'project_id' => Field::advanced($this->projectField()),
                'status' => Field::advanced(Field::select('Show', ['any' => 'All tasks', 'open' => 'Not completed', 'done' => 'Completed'], null, 'any')),
                'limit' => Field::integer('Max results', null, 25, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $status = (string) ($config['status'] ?? 'any');
        $tasks = $this->asana($run, $config, 'GET', '/workspaces/'.$this->workspace($run, $config).'/tasks/search', [
            'text' => filled($config['text'] ?? null) ? trim((string) $config['text']) : null,
            'assignee.any' => filled($config['assignee'] ?? null) ? $this->person($config['assignee']) : null,
            'projects.any' => filled($config['project_id'] ?? null) ? $this->gid($config, 'project_id', 'The project') : null,
            'completed' => in_array($status, ['open', 'done'], true) ? $status === 'done' : null,
            'sort_by' => 'modified_at',
            'limit' => $this->limit($config, 25, 100),
            'opt_fields' => self::TASK_FIELDS,
        ]);
        $found = array_values(array_filter((array) ($tasks['data'] ?? []), 'is_array'));

        return ['tasks' => array_map($this->shapeTask(...), $found), 'count' => count($found)];
    }
}
