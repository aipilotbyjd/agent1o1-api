<?php

namespace App\Nodes\Integrations\ClickUp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ClickUpListTasksNode extends AbstractClickUpNode
{
    public function type(): string
    {
        return 'clickup_list_tasks';
    }

    public function name(): string
    {
        return 'ClickUp: List Tasks';
    }

    public function description(): string
    {
        return 'Lists the tasks of a list, optionally only those created or changed after a time, or in certain statuses.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id'],
            'properties' => [
                ...$this->credentialFields(),
                'workspace_id' => $this->workspaceField(),
                ...$this->locationFields(),
                'statuses' => Field::advanced(Field::list('Only statuses', 'One status name per row, e.g. in progress.', 'in progress')),
                'include_closed' => Field::advanced(Field::boolean('Include closed tasks', null, false)),
                'include_subtasks' => Field::advanced(Field::boolean('Include subtasks', null, false)),
                'updated_after' => Field::advanced(Field::datetime('Changed after')),
                'created_after' => Field::advanced(Field::datetime('Created after')),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $statuses = array_values(array_filter(array_map(fn (mixed $status): string => trim((string) $status), (array) ($config['statuses'] ?? []))));
        $limit = $this->limit($config, 50);
        $tasks = [];

        for ($page = 0; count($tasks) < $limit; $page++) {
            $found = array_values(array_filter((array) ($this->clickup($run, $config, 'GET', '/list/'.$this->numericId($config, 'list_id', 'The list').'/task', [
                'page' => $page,
                'include_closed' => (bool) ($config['include_closed'] ?? false),
                'subtasks' => (bool) ($config['include_subtasks'] ?? false),
                'statuses' => $statuses === [] ? null : $statuses,
                'date_updated_gt' => $this->millis($config, 'updated_after'),
                'date_created_gt' => $this->millis($config, 'created_after'),
                'order_by' => 'created',
                'reverse' => true,
            ])['tasks'] ?? []), 'is_array'));
            array_push($tasks, ...$found);

            // A full page means there may be more.
            if (count($found) < 100) {
                break;
            }
        }

        $tasks = array_slice($tasks, 0, $limit);

        return ['tasks' => array_map($this->shapeTask(...), $tasks), 'count' => count($tasks)];
    }
}
