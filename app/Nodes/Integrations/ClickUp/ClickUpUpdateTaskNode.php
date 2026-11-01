<?php

namespace App\Nodes\Integrations\ClickUp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ClickUpUpdateTaskNode extends AbstractClickUpNode
{
    public function type(): string
    {
        return 'clickup_update_task';
    }

    public function name(): string
    {
        return 'ClickUp: Update Task';
    }

    public function description(): string
    {
        return 'Changes a task\'s name, description, status, priority, due date or assignees. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['task_id'],
            'properties' => [
                ...$this->credentialFields(),
                'task_id' => Field::text('Task', 'The task id, from Create Task or List Tasks.', '86abc123'),
                'name' => Field::text('Name'),
                'description' => Field::textarea('Description'),
                'status' => Field::text('Status', 'A status of the task\'s list, e.g. complete.'),
                'priority' => Field::advanced(Field::select('Priority', ['none' => 'None', 'urgent' => 'Urgent', 'high' => 'High', 'normal' => 'Normal', 'low' => 'Low'], null, 'none')),
                'due_date' => Field::advanced(Field::datetime('Due')),
                'add_assignee_ids' => Field::advanced(Field::list('Add assignees', 'One user id per row.')),
                'remove_assignee_ids' => Field::advanced(Field::list('Remove assignees', 'One user id per row.')),
                'archived' => Field::advanced(Field::select('Archive', ['unchanged' => 'Leave as it is', 'yes' => 'Archive', 'no' => 'Restore'], null, 'unchanged')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->taskId($config);
        $add = $this->numericIds($config['add_assignee_ids'] ?? [], 'An assignee');
        $remove = $this->numericIds($config['remove_assignee_ids'] ?? [], 'An assignee');
        $archived = (string) ($config['archived'] ?? 'unchanged');
        $changes = [
            'name' => filled($config['name'] ?? null) ? trim((string) $config['name']) : null,
            'description' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'status' => filled($config['status'] ?? null) ? trim((string) $config['status']) : null,
            'priority' => $this->priority($config),
            'due_date' => $this->millis($config, 'due_date'),
            'assignees' => $add === [] && $remove === [] ? null : ['add' => array_map('intval', $add), 'rem' => array_map('intval', $remove)],
            'archived' => in_array($archived, ['yes', 'no'], true) ? $archived === 'yes' : null,
        ];

        if (array_filter($changes, fn (mixed $value): bool => $value !== null) === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        return $this->shapeTask($this->clickup($run, $config, 'PUT', "/task/{$id}", [], $changes));
    }
}
