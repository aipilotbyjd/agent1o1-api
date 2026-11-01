<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AsanaRemoveTaskFromProjectNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_remove_task_from_project';
    }

    public function name(): string
    {
        return 'Asana: Remove Task from Project';
    }

    public function description(): string
    {
        return 'Takes a task out of a project. The task itself stays.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['task_id', 'project_id'],
            'properties' => [
                ...$this->credentialFields(),
                'task_id' => Field::text('Task', 'The task id, from Create Task or List Tasks.', '1204567890123456'),
                'workspace_id' => $this->workspaceField(),
                'project_id' => $this->projectField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->gid($config, 'task_id', 'The task');
        $project = $this->gid($config, 'project_id', 'The project');

        $this->asana($run, $config, 'POST', "/tasks/{$id}/removeProject", [], ['project' => $project]);

        return ['removed' => true, 'task_id' => $id, 'project_id' => $project];
    }
}
