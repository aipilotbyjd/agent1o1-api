<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AsanaCompleteTaskNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_complete_task';
    }

    public function name(): string
    {
        return 'Asana: Complete or Reopen Task';
    }

    public function description(): string
    {
        return 'Marks a task completed, or opens it again.';
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
                'state' => Field::select('Mark as', ['done' => 'Completed', 'open' => 'Not completed'], null, 'done'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->gid($config, 'task_id', 'The task');
        $done = ($config['state'] ?? 'done') !== 'open';

        $task = (array) $this->asana($run, $config, 'PUT', "/tasks/{$id}", ['opt_fields' => 'name,completed,completed_at'], ['completed' => $done])['data'];

        return ['id' => $task['gid'] ?? $id, 'name' => $task['name'] ?? null, 'completed' => (bool) ($task['completed'] ?? $done), 'completed_at' => $task['completed_at'] ?? null];
    }
}
