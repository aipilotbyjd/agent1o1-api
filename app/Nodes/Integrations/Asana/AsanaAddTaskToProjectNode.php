<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AsanaAddTaskToProjectNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_add_task_to_project';
    }

    public function name(): string
    {
        return 'Asana: Add Task to Project';
    }

    public function description(): string
    {
        return 'Puts a task in a project, and in a section of it if you pick one. A task can be in many projects.';
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
                'section_id' => Field::advanced(Field::dynamic('Section', 'asana.sections', null, 'To do', dependsOn: ['project_id'])),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->gid($config, 'task_id', 'The task');
        $project = $this->gid($config, 'project_id', 'The project');

        $this->asana($run, $config, 'POST', "/tasks/{$id}/addProject", [], ['project' => $project, 'section' => filled($config['section_id'] ?? null) ? $this->gid($config, 'section_id', 'The section') : null]);

        return ['added' => true, 'task_id' => $id, 'project_id' => $project];
    }
}
