<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AsanaListSubtasksNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_list_subtasks';
    }

    public function name(): string
    {
        return 'Asana: List Subtasks';
    }

    public function description(): string
    {
        return 'Lists the subtasks of a task.';
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
                'task_id' => Field::text('Task', 'The task id, from Create Task or List Tasks.', '1204567890123456'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $tasks = $this->listAll($run, $config, '/tasks/'.$this->gid($config, 'task_id', 'The task').'/subtasks', ['opt_fields' => self::TASK_FIELDS], 100);

        return ['subtasks' => array_map($this->shapeTask(...), $tasks), 'count' => count($tasks)];
    }
}
