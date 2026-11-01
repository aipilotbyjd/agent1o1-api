<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AsanaRemoveTagNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_remove_tag';
    }

    public function name(): string
    {
        return 'Asana: Remove Tag from Task';
    }

    public function description(): string
    {
        return 'Takes a tag off a task.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['task_id', 'tag_id'],
            'properties' => [
                ...$this->credentialFields(),
                'task_id' => Field::text('Task', 'The task id, from Create Task or List Tasks.', '1204567890123456'),
                'workspace_id' => $this->workspaceField(),
                'tag_id' => Field::dynamic('Tag', 'asana.tags', null, 'Urgent', dependsOn: ['workspace_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->gid($config, 'task_id', 'The task');
        $tag = $this->gid($config, 'tag_id', 'The tag');

        $this->asana($run, $config, 'POST', "/tasks/{$id}/removeTag", [], ['tag' => $tag]);

        return ['removed' => true, 'task_id' => $id, 'tag_id' => $tag];
    }
}
