<?php

namespace App\Nodes\Integrations\ClickUp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ClickUpGetTaskNode extends AbstractClickUpNode
{
    public function type(): string
    {
        return 'clickup_get_task';
    }

    public function name(): string
    {
        return 'ClickUp: Get Task';
    }

    public function description(): string
    {
        return 'Reads a task with its status, assignees, tags, dates and subtasks.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['task_id'],
            'properties' => [
                ...$this->credentialFields(),
                'task_id' => Field::text('Task', 'The task id, from Create Task or List Tasks.', '86abc123'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $task = $this->clickup($run, $config, 'GET', '/task/'.$this->taskId($config), ['include_subtasks' => true]);

        return [...$this->shapeTask($task), 'subtasks' => array_values(array_map(fn (array $subtask): array => ['id' => $subtask['id'] ?? null, 'name' => $subtask['name'] ?? null, 'status' => data_get($subtask, 'status.status')], array_filter((array) ($task['subtasks'] ?? []), 'is_array')))];
    }
}
