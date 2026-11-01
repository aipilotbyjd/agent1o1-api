<?php

namespace App\Nodes\Integrations\ClickUp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ClickUpListListsNode extends AbstractClickUpNode
{
    public function type(): string
    {
        return 'clickup_list_lists';
    }

    public function name(): string
    {
        return 'ClickUp: List Lists';
    }

    public function description(): string
    {
        return 'Lists the lists of a folder, or the lists that sit straight in a space.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['space_id'],
            'properties' => [
                ...$this->credentialFields(),
                'workspace_id' => $this->workspaceField(),
                'space_id' => Field::dynamic('Space', 'clickup.spaces', null, 'Marketing', dependsOn: ['workspace_id']),
                'folder_id' => Field::advanced(Field::dynamic('Folder', 'clickup.folders', 'Leave empty for lists straight in the space.', 'Campaigns', dependsOn: ['space_id'])),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $path = filled($config['folder_id'] ?? null) ? '/folder/'.$this->numericId($config, 'folder_id', 'The folder').'/list' : '/space/'.$this->numericId($config, 'space_id', 'The space').'/list';
        $lists = array_values(array_filter((array) ($this->clickup($run, $config, 'GET', $path, ['archived' => false])['lists'] ?? []), 'is_array'));

        return ['lists' => array_map(fn (array $list): array => ['id' => $list['id'] ?? null, 'name' => $list['name'] ?? null, 'task_count' => $list['task_count'] ?? null], $lists), 'count' => count($lists)];
    }
}
