<?php

namespace App\Nodes\Integrations\ClickUp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ClickUpAddTagNode extends AbstractClickUpNode
{
    public function type(): string
    {
        return 'clickup_add_tag';
    }

    public function name(): string
    {
        return 'ClickUp: Add Tag to Task';
    }

    public function description(): string
    {
        return 'Puts a tag on a task. The tag must already exist in the space.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['task_id', 'tag'],
            'properties' => [
                ...$this->credentialFields(),
                'task_id' => Field::text('Task', 'The task id, from Create Task or List Tasks.', '86abc123'),
                'tag' => Field::text('Tag name', null, 'Urgent'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->taskId($config);
        $tag = $this->requiredString($config, 'tag', 'The tag');

        $this->clickup($run, $config, 'POST', "/task/{$id}/tag/".rawurlencode($tag));

        return ['added' => true, 'task_id' => $id, 'tag' => $tag];
    }
}
