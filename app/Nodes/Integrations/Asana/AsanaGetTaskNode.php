<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AsanaGetTaskNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_get_task';
    }

    public function name(): string
    {
        return 'Asana: Get Task';
    }

    public function description(): string
    {
        return 'Reads a task with its project, section, tags and assignee.';
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
        return $this->shapeTask((array) $this->asana($run, $config, 'GET', '/tasks/'.$this->gid($config, 'task_id', 'The task'), ['opt_fields' => self::TASK_FIELDS.',assignee.gid,parent.gid,projects.gid'])['data']);
    }
}
