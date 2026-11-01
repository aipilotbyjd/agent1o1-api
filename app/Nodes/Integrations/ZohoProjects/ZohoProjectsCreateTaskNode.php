<?php

namespace App\Nodes\Integrations\ZohoProjects;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoProjectsCreateTaskNode extends AbstractZohoProjectsNode
{
    public function type(): string
    {
        return 'zoho_projects_create_task';
    }

    public function name(): string
    {
        return 'Zoho Projects: Create Task';
    }

    public function description(): string
    {
        return 'Adds a task to a project, optionally assigned and with dates.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => $this->projectField(),
                'name' => Field::text('Task name', null, 'Draft the launch email'),
                'description' => Field::textarea('Description'),
                'owner_id' => Field::advanced($this->userField()),
                'priority' => Field::advanced(Field::select('Priority', ['None' => 'None', 'Low' => 'Low', 'Medium' => 'Medium', 'High' => 'High'])),
                'start_date' => Field::advanced(Field::datetime('Start')),
                'due_date' => Field::advanced(Field::datetime('Due')),
                'task_list_id' => Field::advanced(Field::text('Task list id', 'A long number. Empty puts it in the default list.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $project = $this->numericId($config, 'project_id', 'The project');
        $owner = $this->optionalId($config, 'owner_id', 'The assignee');
        $list = $this->optionalId($config, 'task_list_id', 'The task list');

        $task = $this->projects($run, $config, 'POST', "/projects/{$project}/tasks", [], array_filter([
            'name' => $this->requiredString($config, 'name', 'The task name'),
            'description' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'owners_and_work' => $owner === null ? null : ['owners' => [['add' => [$owner]]]],
            'priority' => in_array($config['priority'] ?? null, ['None', 'Low', 'Medium', 'High'], true) ? $config['priority'] : null,
            'start_date' => $this->date($config, 'start_date'),
            'end_date' => $this->date($config, 'due_date'),
            'tasklist' => $list === null ? null : ['id' => $list],
        ], fn (mixed $value): bool => $value !== null));

        return $this->shapeTask((array) ($task['tasks'][0] ?? $task));
    }
}
