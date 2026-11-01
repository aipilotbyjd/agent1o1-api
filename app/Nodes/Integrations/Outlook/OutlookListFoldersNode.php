<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class OutlookListFoldersNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_list_folders';
    }

    public function name(): string
    {
        return 'Outlook: List Mail Folders';
    }

    public function description(): string
    {
        return 'Lists the mail folders with their unread and total counts.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'parent' => Field::advanced(Field::dynamic('Inside folder', 'outlook.folders', 'Leave empty for the top level.', 'inbox')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $path = filled($config['parent'] ?? null) ? '/me/mailFolders/'.rawurlencode(trim((string) $config['parent'])).'/childFolders' : '/me/mailFolders';
        $found = $this->get($run, $path, $config, ['$top' => 100]);
        $folders = array_map(fn (array $folder): array => ['id' => $folder['id'] ?? null, 'name' => $folder['displayName'] ?? null, 'unread' => $folder['unreadItemCount'] ?? null, 'total' => $folder['totalItemCount'] ?? null, 'children' => $folder['childFolderCount'] ?? null], array_values(array_filter((array) ($found['value'] ?? []), 'is_array')));

        return ['folders' => $folders, 'count' => count($folders)];
    }
}
