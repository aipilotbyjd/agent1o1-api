<?php

namespace App\Nodes\Integrations\ClickUp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ClickUpDeleteTaskNode extends AbstractClickUpNode
{
    public function type(): string
    {
        return 'clickup_delete_task';
    }

    public function name(): string
    {
        return 'ClickUp: Delete Task';
    }

    public function description(): string
    {
        return 'Deletes a task for good.';
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
                'task_id' => Field::text('Task', 'The task id, from Create Task or List Tasks.', '86abc123'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->taskId($config);

        $this->clickup($run, $config, 'DELETE', "/task/{$id}");

        return ['deleted' => true, 'task_id' => $id];
    }
}
