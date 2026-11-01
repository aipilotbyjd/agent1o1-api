<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class OutlookCreateFolderNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_create_folder';
    }

    public function name(): string
    {
        return 'Outlook: Create Mail Folder';
    }

    public function description(): string
    {
        return 'Creates a mail folder, at the top level or inside another.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name', null, 'Clients'),
                'parent' => Field::advanced(Field::dynamic('Inside folder', 'outlook.folders', 'Leave empty for the top level.', 'inbox')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $name = $this->requiredText($config, 'name', 'The name');
        $path = filled($config['parent'] ?? null) ? '/me/mailFolders/'.rawurlencode(trim((string) $config['parent'])).'/childFolders' : '/me/mailFolders';
        $folder = $this->post($run, $path, $config, ['displayName' => $name]);

        return ['id' => $folder['id'] ?? null, 'name' => $folder['displayName'] ?? $name, 'parent_id' => $folder['parentFolderId'] ?? null];
    }
}
