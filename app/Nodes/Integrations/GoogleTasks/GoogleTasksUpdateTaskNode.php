<?php

namespace App\Nodes\Integrations\GoogleTasks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class GoogleTasksUpdateTaskNode extends AbstractGoogleTasksNode
{
    public function type(): string
    {
        return 'google_tasks_update_task';
    }

    public function name(): string
    {
        return 'Google Tasks: Update Task';
    }

    public function description(): string
    {
        return 'Changes a task, or marks it done or not done. Fields you leave empty stay as they are.';
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
                'task_list_id' => Field::dynamic('Task list', 'google_tasks.lists', 'Defaults to your main list.', '@default'),
                'task_id' => Field::dynamic('Task', 'google_tasks.tasks', 'Pick a task, or map a task ID from an earlier step.', uses: ['task_list_id']),
                'title' => Field::text('Title', 'Leave empty to keep it.'),
                'notes' => Field::textarea('Notes', 'Leave empty to keep them.'),
                'due' => Field::datetime('Due date', 'Leave empty to keep it. Only the date is kept.'),
                'status' => Field::select('Status', [
                    'completed' => 'Done',
                    'needsAction' => 'Not done',
                ], 'Leave empty to keep it as it is.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $status = trim((string) ($config['status'] ?? ''));

        $body = array_filter([
            'title' => trim((string) ($config['title'] ?? '')) ?: null,
            'notes' => trim((string) ($config['notes'] ?? '')) ?: null,
            'due' => $this->dueDate($config['due'] ?? null),
            'status' => $status !== '' ? $status : null,
        ], fn (mixed $value): bool => $value !== null);

        // Reopening a task must also clear the time it was finished (JSON null).
        if ($status === 'needsAction') {
            $body['completed'] = null;
        }

        if ($body === []) {
            throw new InvalidArgumentException('Google Tasks: choose at least one field to change.');
        }

        return $this->patch($run, $this->tasksPath($config, (string) $config['task_id']), $config, $body);
    }
}
