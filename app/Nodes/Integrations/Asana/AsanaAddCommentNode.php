<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AsanaAddCommentNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_add_comment';
    }

    public function name(): string
    {
        return 'Asana: Add Comment';
    }

    public function description(): string
    {
        return 'Comments on a task.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['task_id', 'text'],
            'properties' => [
                ...$this->credentialFields(),
                'task_id' => Field::text('Task', 'The task id, from Create Task or List Tasks.', '1204567890123456'),
                'text' => Field::textarea('Comment'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->gid($config, 'task_id', 'The task');
        $story = (array) $this->asana($run, $config, 'POST', "/tasks/{$id}/stories", ['opt_fields' => 'text,created_at,created_by.name'], ['text' => $this->requiredString($config, 'text', 'The comment')])['data'];

        return ['id' => $story['gid'] ?? null, 'task_id' => $id, 'text' => $story['text'] ?? null, 'created_at' => $story['created_at'] ?? null, 'author' => data_get($story, 'created_by.name')];
    }
}
