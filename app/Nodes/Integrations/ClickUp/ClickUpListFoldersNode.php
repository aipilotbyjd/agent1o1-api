<?php

namespace App\Nodes\Integrations\ClickUp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ClickUpListFoldersNode extends AbstractClickUpNode
{
    public function type(): string
    {
        return 'clickup_list_folders';
    }

    public function name(): string
    {
        return 'ClickUp: List Folders';
    }

    public function description(): string
    {
        return 'Lists the folders of a space, with their lists.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $folders = array_values(array_filter((array) ($this->clickup($run, $config, 'GET', '/space/'.$this->numericId($config, 'space_id', 'The space').'/folder', ['archived' => false])['folders'] ?? []), 'is_array'));

        return ['folders' => array_map(fn (array $folder): array => ['id' => $folder['id'] ?? null, 'name' => $folder['name'] ?? null, 'lists' => array_values(array_map(fn (array $list): array => ['id' => $list['id'] ?? null, 'name' => $list['name'] ?? null], array_filter((array) ($folder['lists'] ?? []), 'is_array')))], $folders), 'count' => count($folders)];
    }
}
