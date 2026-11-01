<?php

namespace App\Nodes\Integrations\ClickUp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ClickUpCreateListNode extends AbstractClickUpNode
{
    public function type(): string
    {
        return 'clickup_create_list';
    }

    public function name(): string
    {
        return 'ClickUp: Create List';
    }

    public function description(): string
    {
        return 'Creates a list in a folder, or straight in a space.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['space_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'workspace_id' => $this->workspaceField(),
                'space_id' => Field::dynamic('Space', 'clickup.spaces', null, 'Marketing', dependsOn: ['workspace_id']),
                'folder_id' => Field::advanced(Field::dynamic('Folder', 'clickup.folders', 'Leave empty to put it straight in the space.', 'Campaigns', dependsOn: ['space_id'])),
                'name' => Field::text('Name', null, 'Q2 launch'),
                'content' => Field::textarea('Description'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $path = filled($config['folder_id'] ?? null) ? '/folder/'.$this->numericId($config, 'folder_id', 'The folder').'/list' : '/space/'.$this->numericId($config, 'space_id', 'The space').'/list';
        $list = $this->clickup($run, $config, 'POST', $path, [], ['name' => $this->requiredString($config, 'name', 'The name'), 'content' => filled($config['content'] ?? null) ? (string) $config['content'] : null]);

        return ['id' => $list['id'] ?? null, 'name' => $list['name'] ?? null];
    }
}
