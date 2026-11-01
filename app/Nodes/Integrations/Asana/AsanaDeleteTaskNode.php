<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AsanaDeleteTaskNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_delete_task';
    }

    public function name(): string
    {
        return 'Asana: Delete Task';
    }

    public function description(): string
    {
        return 'Deletes a task. Asana keeps it in the deleted items for 30 days.';
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
                'task_id' => Field::text('Task', 'The task id, from Create Task or List Tasks.', '1204567890123456'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->gid($config, 'task_id', 'The task');

        $this->asana($run, $config, 'DELETE', "/tasks/{$id}");

        return ['deleted' => true, 'task_id' => $id];
    }
}
