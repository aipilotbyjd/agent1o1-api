<?php

namespace App\Nodes\Integrations\ClickUp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ClickUpGetListNode extends AbstractClickUpNode
{
    public function type(): string
    {
        return 'clickup_get_list';
    }

    public function name(): string
    {
        return 'ClickUp: Get List';
    }

    public function description(): string
    {
        return 'Reads a list with its statuses, which Create Task and Update Task take by name.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $list = $this->clickup($run, $config, 'GET', '/list/'.$this->numericId($config, 'list_id', 'The list'));

        return ['id' => $list['id'] ?? null, 'name' => $list['name'] ?? null, 'task_count' => $list['task_count'] ?? null, 'statuses' => array_values(array_map(fn (array $status): array => ['name' => $status['status'] ?? null, 'type' => $status['type'] ?? null], array_filter((array) ($list['statuses'] ?? []), 'is_array'))), 'folder' => data_get($list, 'folder.name'), 'space' => data_get($list, 'space.name')];
    }
}
