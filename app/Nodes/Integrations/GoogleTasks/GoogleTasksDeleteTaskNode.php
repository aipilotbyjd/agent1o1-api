<?php

namespace App\Nodes\Integrations\GoogleTasks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleTasksDeleteTaskNode extends AbstractGoogleTasksNode
{
    public function type(): string
    {
        return 'google_tasks_delete_task';
    }

    public function name(): string
    {
        return 'Google Tasks: Delete Task';
    }

    public function description(): string
    {
        return 'Deletes a task.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->delete($run, $this->tasksPath($config, (string) $config['task_id']), $config);

        return ['deleted' => true, 'task_id' => $config['task_id']];
    }
}
