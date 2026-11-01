<?php

namespace App\Nodes\Integrations\ZohoProjects;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ZohoProjectsUpdateTaskNode extends AbstractZohoProjectsNode
{
    public function type(): string
    {
        return 'zoho_projects_update_task';
    }

    public function name(): string
    {
        return 'Zoho Projects: Update Task';
    }

    public function description(): string
    {
        return 'Changes a task. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'task_id'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => $this->projectField(),
                'task_id' => Field::text('Task id', 'An id from a previous step (a long number).'),
                'name' => Field::text('Task name'),
                'description' => Field::textarea('Description'),
                'owner_id' => Field::advanced($this->userField()),
                'priority' => Field::advanced(Field::select('Priority', ['None' => 'None', 'Low' => 'Low', 'Medium' => 'Medium', 'High' => 'High'])),
                'percent_complete' => Field::advanced(Field::integer('Percent complete', '100 completes the task.', null, 0, 100)),
                'due_date' => Field::advanced(Field::datetime('Due')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $project = $this->numericId($config, 'project_id', 'The project');
        $id = $this->numericId($config, 'task_id', 'The task');
        $owner = $this->optionalId($config, 'owner_id', 'The assignee');
        $body = array_filter([
            'name' => filled($config['name'] ?? null) ? trim((string) $config['name']) : null,
            'description' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'owners_and_work' => $owner === null ? null : ['owners' => [['add' => [$owner]]]],
            'priority' => in_array($config['priority'] ?? null, ['None', 'Low', 'Medium', 'High'], true) ? $config['priority'] : null,
            'percent_complete' => filled($config['percent_complete'] ?? null) ? max(0, min(100, (int) $config['percent_complete'])) : null,
            'end_date' => $this->date($config, 'due_date'),
        ], fn (mixed $value): bool => $value !== null);

        if ($body === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        $task = $this->projects($run, $config, 'PATCH', "/projects/{$project}/tasks/{$id}", [], $body);

        return $this->shapeTask((array) ($task['tasks'][0] ?? ['id' => $id]));
    }
}
