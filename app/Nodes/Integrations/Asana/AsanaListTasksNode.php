<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class AsanaListTasksNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_list_tasks';
    }

    public function name(): string
    {
        return 'Asana: List Tasks';
    }

    public function description(): string
    {
        return 'Lists the tasks of a project or section, or the tasks assigned to someone, optionally only those changed lately.';
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
                'project_id' => Field::advanced($this->projectField()),
                'section_id' => Field::advanced(Field::dynamic('Section', 'asana.sections', null, 'To do', dependsOn: ['project_id'])),
                'assignee' => Field::advanced(Field::text('Assigned to', 'me, or a user id. Needs a workspace.')),
                'status' => Field::advanced(Field::select('Show', ['any' => 'All tasks', 'open' => 'Not completed', 'done' => 'Completed'], null, 'any')),
                'modified_after' => Field::advanced(Field::datetime('Changed after')),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $status = (string) ($config['status'] ?? 'any');
        $query = ['opt_fields' => self::TASK_FIELDS, 'modified_since' => filled($config['modified_after'] ?? null) ? Carbon::parse((string) $config['modified_after'])->utc()->toIso8601String() : null, 'completed_since' => $status === 'open' ? 'now' : null];

        if (filled($config['section_id'] ?? null)) {
            $path = '/sections/'.$this->gid($config, 'section_id', 'The section').'/tasks';
        } elseif (filled($config['project_id'] ?? null)) {
            $path = '/projects/'.$this->gid($config, 'project_id', 'The project').'/tasks';
        } elseif (filled($config['assignee'] ?? null)) {
            $path = '/tasks';
            $query += ['assignee' => $this->person($config['assignee']), 'workspace' => $this->workspace($run, $config)];
        } else {
            throw new InvalidArgumentException('Pick a project, a section or an assignee to list tasks of.');
        }

        $tasks = $this->listAll($run, $config, $path, $query, $this->limit($config, 50));

        if ($status === 'done') {
            $tasks = array_values(array_filter($tasks, fn (array $task): bool => (bool) ($task['completed'] ?? false)));
        }

        return ['tasks' => array_map($this->shapeTask(...), $tasks), 'count' => count($tasks)];
    }
}
