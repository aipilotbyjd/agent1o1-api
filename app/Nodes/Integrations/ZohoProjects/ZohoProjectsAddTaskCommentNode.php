<?php

namespace App\Nodes\Integrations\ZohoProjects;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoProjectsAddTaskCommentNode extends AbstractZohoProjectsNode
{
    public function type(): string
    {
        return 'zoho_projects_add_task_comment';
    }

    public function name(): string
    {
        return 'Zoho Projects: Add Task Comment';
    }

    public function description(): string
    {
        return 'Adds a comment to a task.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'task_id', 'content'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => $this->projectField(),
                'task_id' => Field::text('Task id', 'An id from a previous step (a long number).'),
                'content' => Field::textarea('Comment'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $project = $this->numericId($config, 'project_id', 'The project');
        $id = $this->numericId($config, 'task_id', 'The task');
        $comment = $this->projects($run, $config, 'POST', "/projects/{$project}/tasks/{$id}/comments", [], ['content' => $this->requiredString($config, 'content', 'The comment')]);

        return ['added' => true, 'task_id' => $id, 'id' => isset($comment['comments'][0]['id']) ? (string) $comment['comments'][0]['id'] : null];
    }
}
