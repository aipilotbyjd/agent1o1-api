<?php

namespace App\Nodes\Integrations\ClickUp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ClickUpFilterTasksNode extends AbstractClickUpNode
{
    public function type(): string
    {
        return 'clickup_filter_tasks';
    }

    public function name(): string
    {
        return 'ClickUp: Find Tasks in Workspace';
    }

    public function description(): string
    {
        return 'Finds tasks across a whole workspace by assignee, status, list or dates.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workspace_id'],
            'properties' => [
                ...$this->credentialFields(),
                'workspace_id' => $this->workspaceField(),
                'list_ids' => Field::advanced(Field::list('Only lists', 'One list id per row.')),
                'assignee_ids' => Field::advanced(Field::list('Assigned to', 'One user id per row.')),
                'statuses' => Field::advanced(Field::list('Only statuses', 'One status name per row.')),
                'due_before' => Field::advanced(Field::datetime('Due before')),
                'updated_after' => Field::advanced(Field::datetime('Changed after')),
                'include_closed' => Field::advanced(Field::boolean('Include closed tasks', null, false)),
                'limit' => Field::integer('Max results', null, 50, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $found = $this->clickup($run, $config, 'GET', '/team/'.$this->numericId($config, 'workspace_id', 'The workspace').'/task', [
            'list_ids' => $this->numericIds($config['list_ids'] ?? [], 'A list') ?: null,
            'assignees' => $this->numericIds($config['assignee_ids'] ?? [], 'A user') ?: null,
            'statuses' => array_values(array_filter(array_map(fn (mixed $status): string => trim((string) $status), (array) ($config['statuses'] ?? [])))) ?: null,
            'due_date_lt' => $this->millis($config, 'due_before'),
            'date_updated_gt' => $this->millis($config, 'updated_after'),
            'include_closed' => (bool) ($config['include_closed'] ?? false),
            'order_by' => 'updated',
            'reverse' => true,
        ]);
        $tasks = array_slice(array_values(array_filter((array) ($found['tasks'] ?? []), 'is_array')), 0, $this->limit($config, 50, 100));

        return ['tasks' => array_map($this->shapeTask(...), $tasks), 'count' => count($tasks)];
    }
}
