<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AsanaMoveTaskToSectionNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_move_task_to_section';
    }

    public function name(): string
    {
        return 'Asana: Move Task to Section';
    }

    public function description(): string
    {
        return 'Moves a task to a section of one of its projects.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['task_id', 'section_id'],
            'properties' => [
                ...$this->credentialFields(),
                'task_id' => Field::text('Task', 'The task id, from Create Task or List Tasks.', '1204567890123456'),
                'workspace_id' => $this->workspaceField(),
                'project_id' => $this->projectField(),
                'section_id' => Field::dynamic('Section', 'asana.sections', null, 'Done', dependsOn: ['project_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->gid($config, 'task_id', 'The task');
        $section = $this->gid($config, 'section_id', 'The section');

        $this->asana($run, $config, 'POST', "/sections/{$section}/addTask", [], ['task' => $id]);

        return ['moved' => true, 'task_id' => $id, 'section_id' => $section];
    }
}
