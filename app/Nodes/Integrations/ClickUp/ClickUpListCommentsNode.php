<?php

namespace App\Nodes\Integrations\ClickUp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ClickUpListCommentsNode extends AbstractClickUpNode
{
    public function type(): string
    {
        return 'clickup_list_comments';
    }

    public function name(): string
    {
        return 'ClickUp: List Comments';
    }

    public function description(): string
    {
        return 'Lists the comments on a task, newest first.';
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
                'task_id' => Field::text('Task', 'The task id, from Create Task or List Tasks.', '86abc123'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $comments = array_values(array_filter((array) ($this->clickup($run, $config, 'GET', '/task/'.$this->taskId($config).'/comment')['comments'] ?? []), 'is_array'));

        return ['comments' => array_map(fn (array $comment): array => ['id' => isset($comment['id']) ? (string) $comment['id'] : null, 'text' => $comment['comment_text'] ?? null, 'author' => data_get($comment, 'user.username'), 'date' => $this->isoFromMillis($comment['date'] ?? null), 'resolved' => (bool) ($comment['resolved'] ?? false)], $comments), 'count' => count($comments)];
    }
}
