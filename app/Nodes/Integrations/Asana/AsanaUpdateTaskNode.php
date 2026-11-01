<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class AsanaUpdateTaskNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_update_task';
    }

    public function name(): string
    {
        return 'Asana: Update Task';
    }

    public function description(): string
    {
        return 'Changes a task\'s name, description, assignee, due date or whether it is done. Only what is filled in changes.';
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
                'task_id' => Field::text('Task', 'The task id, from Create Task or List Tasks.', '1204567890123456'),
                'name' => Field::text('Name'),
                'notes' => Field::textarea('Description'),
                'assignee' => Field::advanced(Field::text('Assignee', 'me, an email address, or a user id.', 'me')),
                'due_on' => Field::advanced(Field::text('Due date', 'A day, like 2026-03-31.')),
                'completed' => Field::advanced(Field::select('Status', ['unchanged' => 'Leave as it is', 'done' => 'Completed', 'open' => 'Not completed'], null, 'unchanged')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->gid($config, 'task_id', 'The task');
        $completed = (string) ($config['completed'] ?? 'unchanged');
        $changes = [
            'name' => filled($config['name'] ?? null) ? trim((string) $config['name']) : null,
            'notes' => filled($config['notes'] ?? null) ? (string) $config['notes'] : null,
            'assignee' => filled($config['assignee'] ?? null) ? $this->person($config['assignee']) : null,
            'due_on' => $this->day($config, 'due_on'),
            'completed' => in_array($completed, ['done', 'open'], true) ? $completed === 'done' : null,
        ];

        if (array_filter($changes, fn (mixed $value): bool => $value !== null) === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        return $this->shapeTask((array) $this->asana($run, $config, 'PUT', "/tasks/{$id}", ['opt_fields' => self::TASK_FIELDS], $changes)['data']);
    }
}
