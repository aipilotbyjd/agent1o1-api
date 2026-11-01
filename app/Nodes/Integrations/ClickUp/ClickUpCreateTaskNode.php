<?php

namespace App\Nodes\Integrations\ClickUp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ClickUpCreateTaskNode extends AbstractClickUpNode
{
    public function type(): string
    {
        return 'clickup_create_task';
    }

    public function name(): string
    {
        return 'ClickUp: Create Task';
    }

    public function description(): string
    {
        return 'Creates a task in a list, with a status, priority, assignees and dates.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'workspace_id' => $this->workspaceField(),
                ...$this->locationFields(),
                'name' => Field::text('Name', null, 'Write the launch email'),
                'description' => Field::textarea('Description'),
                'status' => Field::advanced(Field::text('Status', 'A status of the list, e.g. to do. Empty uses the list\'s first.')),
                'priority' => Field::advanced(Field::select('Priority', ['none' => 'None', 'urgent' => 'Urgent', 'high' => 'High', 'normal' => 'Normal', 'low' => 'Low'], null, 'none')),
                'assignee_ids' => Field::advanced(Field::list('Assignees', 'One user id per row, from List Workspaces.')),
                'tags' => Field::advanced(Field::list('Tags', 'One tag name per row.')),
                'due_date' => Field::advanced(Field::datetime('Due')),
                'start_date' => Field::advanced(Field::datetime('Start')),
                'parent_id' => Field::advanced(Field::text('Parent task', 'Makes this a subtask of that task.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $assignees = $this->numericIds($config['assignee_ids'] ?? [], 'An assignee');
        $tags = array_values(array_filter(array_map(fn (mixed $tag): string => trim((string) $tag), (array) ($config['tags'] ?? []))));

        $task = $this->clickup($run, $config, 'POST', '/list/'.$this->numericId($config, 'list_id', 'The list').'/task', [], [
            'name' => $this->requiredString($config, 'name', 'The name'),
            'description' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'status' => filled($config['status'] ?? null) ? trim((string) $config['status']) : null,
            'priority' => $this->priority($config),
            'assignees' => $assignees === [] ? null : array_map('intval', $assignees),
            'tags' => $tags === [] ? null : $tags,
            'due_date' => $this->millis($config, 'due_date'),
            'start_date' => $this->millis($config, 'start_date'),
            'parent' => filled($config['parent_id'] ?? null) ? $this->taskId($config, 'parent_id') : null,
        ]);

        return $this->shapeTask($task);
    }
}
