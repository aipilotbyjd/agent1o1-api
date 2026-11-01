<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AsanaListCommentsNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_list_comments';
    }

    public function name(): string
    {
        return 'Asana: List Comments';
    }

    public function description(): string
    {
        return 'Lists the comments on a task, oldest first. Activity entries (a task moved, completed) are left out.';
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
                'limit' => Field::integer('Max results', null, 50, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $stories = $this->listAll($run, $config, '/tasks/'.$this->gid($config, 'task_id', 'The task').'/stories', ['opt_fields' => 'type,resource_subtype,text,created_at,created_by.name'], 500);
        $comments = array_values(array_filter($stories, fn (array $story): bool => ($story['type'] ?? '') === 'comment' || ($story['resource_subtype'] ?? '') === 'comment_added'));
        $comments = array_map(fn (array $story): array => ['id' => $story['gid'] ?? null, 'text' => $story['text'] ?? null, 'created_at' => $story['created_at'] ?? null, 'author' => data_get($story, 'created_by.name')], array_slice($comments, 0, $this->limit($config, 50, 500)));

        return ['comments' => $comments, 'count' => count($comments)];
    }
}
