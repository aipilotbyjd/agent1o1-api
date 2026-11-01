<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AsanaAddTagNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_add_tag';
    }

    public function name(): string
    {
        return 'Asana: Add Tag to Task';
    }

    public function description(): string
    {
        return 'Puts a tag on a task.';
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

        $this->asana($run, $config, 'POST', "/tasks/{$id}/addTag", [], ['tag' => $tag]);

        return ['added' => true, 'task_id' => $id, 'tag_id' => $tag];
    }
}
