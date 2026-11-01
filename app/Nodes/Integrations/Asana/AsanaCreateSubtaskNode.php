<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AsanaCreateSubtaskNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_create_subtask';
    }

    public function name(): string
    {
        return 'Asana: Create Subtask';
    }

    public function description(): string
    {
        return 'Adds a subtask under a task.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['task_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'task_id' => Field::text('Task', 'The task id, from Create Task or List Tasks.', '1204567890123456'),
                'name' => Field::text('Name'),
                'notes' => Field::textarea('Description'),
                'assignee' => Field::advanced(Field::text('Assignee', 'me, an email address, or a user id.', 'me')),
                'due_on' => Field::advanced(Field::text('Due date', 'A day, like 2026-03-31.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->gid($config, 'task_id', 'The task');
        $task = (array) $this->asana($run, $config, 'POST', "/tasks/{$id}/subtasks", ['opt_fields' => self::TASK_FIELDS], [
            'name' => $this->requiredString($config, 'name', 'The name'),
            'notes' => filled($config['notes'] ?? null) ? (string) $config['notes'] : null,
            'assignee' => filled($config['assignee'] ?? null) ? $this->person($config['assignee']) : null,
            'due_on' => $this->day($config, 'due_on'),
        ])['data'];

        return $this->shapeTask($task);
    }
}
