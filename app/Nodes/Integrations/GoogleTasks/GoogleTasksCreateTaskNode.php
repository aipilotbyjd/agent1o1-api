<?php

namespace App\Nodes\Integrations\GoogleTasks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleTasksCreateTaskNode extends AbstractGoogleTasksNode
{
    public function type(): string
    {
        return 'google_tasks_create_task';
    }

    public function name(): string
    {
        return 'Google Tasks: Create Task';
    }

    public function description(): string
    {
        return 'Adds a task to a task list.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title'],
            'properties' => [
                ...$this->credentialFields(),
                'task_list_id' => Field::dynamic('Task list', 'google_tasks.lists', 'Defaults to your main list.', '@default'),
                'title' => Field::text('Title', null, 'Send the invoice'),
                'notes' => Field::textarea('Notes'),
                'due' => Field::datetime('Due date', 'Only the date is kept.'),
                'parent_id' => Field::dynamic('Parent task', 'google_tasks.tasks', 'Makes this a subtask.', uses: ['task_list_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $parent = trim((string) ($config['parent_id'] ?? ''));
        $endpoint = $this->tasksPath($config).($parent !== '' ? '?'.http_build_query(['parent' => $parent]) : '');

        return $this->post($run, $endpoint, $config, array_filter([
            'title' => $config['title'],
            'notes' => trim((string) ($config['notes'] ?? '')) ?: null,
            'due' => $this->dueDate($config['due'] ?? null),
        ]));
    }
}
