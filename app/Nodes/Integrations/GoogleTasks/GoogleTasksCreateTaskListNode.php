<?php

namespace App\Nodes\Integrations\GoogleTasks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleTasksCreateTaskListNode extends AbstractGoogleTasksNode
{
    public function type(): string
    {
        return 'google_tasks_create_task_list';
    }

    public function name(): string
    {
        return 'Google Tasks: Create Task List';
    }

    public function description(): string
    {
        return 'Creates a new task list.';
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
                'title' => Field::text('List name', null, 'Groceries'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, '/users/@me/lists', $config, ['title' => $config['title']]);
    }
}
