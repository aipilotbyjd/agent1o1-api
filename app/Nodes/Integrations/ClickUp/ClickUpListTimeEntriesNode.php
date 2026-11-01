<?php

namespace App\Nodes\Integrations\ClickUp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ClickUpListTimeEntriesNode extends AbstractClickUpNode
{
    public function type(): string
    {
        return 'clickup_list_time_entries';
    }

    public function name(): string
    {
        return 'ClickUp: List Time Entries';
    }

    public function description(): string
    {
        return 'Lists the time entries of a workspace in a date range (the last 30 days by default).';
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
                'from' => Field::advanced(Field::datetime('From')),
                'to' => Field::advanced(Field::datetime('To')),
                'assignee_id' => Field::advanced(Field::text('Whose time', 'A user id. Empty is the connected user.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $found = $this->clickup($run, $config, 'GET', '/team/'.$this->numericId($config, 'workspace_id', 'The workspace').'/time_entries', [
            'start_date' => $this->millis($config, 'from'),
            'end_date' => $this->millis($config, 'to'),
            'assignee' => filled($config['assignee_id'] ?? null) ? $this->numericId($config, 'assignee_id', 'The user') : null,
        ]);
        $entries = array_values(array_filter((array) ($found['data'] ?? []), 'is_array'));

        return ['entries' => array_map(fn (array $entry): array => ['id' => $entry['id'] ?? null, 'task_id' => data_get($entry, 'task.id'), 'task' => data_get($entry, 'task.name'), 'user' => data_get($entry, 'user.username'), 'minutes' => isset($entry['duration']) ? (int) round(((int) $entry['duration']) / 60000) : null, 'started_at' => $this->isoFromMillis($entry['start'] ?? null), 'description' => $entry['description'] ?? null], $entries), 'count' => count($entries)];
    }
}
