<?php

namespace App\Nodes\Integrations\ClickUp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ClickUpCreateTimeEntryNode extends AbstractClickUpNode
{
    public function type(): string
    {
        return 'clickup_create_time_entry';
    }

    public function name(): string
    {
        return 'ClickUp: Log Time';
    }

    public function description(): string
    {
        return 'Records time spent on a task.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workspace_id', 'task_id', 'minutes'],
            'properties' => [
                ...$this->credentialFields(),
                'workspace_id' => $this->workspaceField(),
                'task_id' => Field::text('Task', 'The task id, from Create Task or List Tasks.', '86abc123'),
                'minutes' => Field::integer('Minutes', null, 30, 1, 1440),
                'started_at' => Field::advanced(Field::datetime('Started', 'Empty is now.')),
                'description' => Field::advanced(Field::text('Note')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $workspace = $this->numericId($config, 'workspace_id', 'The workspace');
        $minutes = (int) ($config['minutes'] ?? 0);

        if ($minutes < 1 || $minutes > 1440) {
            throw new InvalidArgumentException('Minutes must be between 1 and 1440.');
        }

        $entry = (array) ($this->clickup($run, $config, 'POST', "/team/{$workspace}/time_entries", [], [
            'tid' => $this->taskId($config),
            'start' => $this->millis($config, 'started_at') ?? now()->getTimestampMs(),
            'duration' => $minutes * 60000,
            'description' => filled($config['description'] ?? null) ? trim((string) $config['description']) : null,
        ])['data'] ?? []);

        return ['id' => $entry['id'] ?? null, 'task_id' => trim((string) $config['task_id']), 'minutes' => $minutes];
    }
}
