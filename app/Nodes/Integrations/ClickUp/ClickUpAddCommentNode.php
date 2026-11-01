<?php

namespace App\Nodes\Integrations\ClickUp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ClickUpAddCommentNode extends AbstractClickUpNode
{
    public function type(): string
    {
        return 'clickup_add_comment';
    }

    public function name(): string
    {
        return 'ClickUp: Add Comment';
    }

    public function description(): string
    {
        return 'Comments on a task, optionally assigning the comment to someone.';
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
                'task_id' => Field::text('Task', 'The task id, from Create Task or List Tasks.', '86abc123'),
                'text' => Field::textarea('Comment'),
                'assignee_id' => Field::advanced(Field::text('Assign the comment to', 'A user id.')),
                'notify_all' => Field::advanced(Field::boolean('Notify everyone on the task', null, false)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->taskId($config);
        $comment = $this->clickup($run, $config, 'POST', "/task/{$id}/comment", [], [
            'comment_text' => $this->requiredString($config, 'text', 'The comment'),
            'assignee' => filled($config['assignee_id'] ?? null) ? (int) $this->numericId($config, 'assignee_id', 'The assignee') : null,
            'notify_all' => (bool) ($config['notify_all'] ?? false),
        ]);

        return ['id' => isset($comment['id']) ? (string) $comment['id'] : null, 'task_id' => $id, 'date' => $this->isoFromMillis($comment['date'] ?? null)];
    }
}
