<?php

namespace App\Nodes\Integrations\ZohoMail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ZohoMailListFoldersNode extends AbstractZohoMailNode
{
    public function type(): string
    {
        return 'zoho_mail_list_folders';
    }

    public function name(): string
    {
        return 'Zoho Mail: List Folders';
    }

    public function description(): string
    {
        return 'Lists the folders of the mail account, with their ids.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $folders = (array) ($this->account($run, $config, 'GET', '/folders')['data'] ?? []);

        return ['folders' => array_map(fn (array $folder): array => ['id' => isset($folder['folderId']) ? (string) $folder['folderId'] : null, 'name' => $folder['folderName'] ?? null, 'type' => $folder['folderType'] ?? null, 'path' => $folder['path'] ?? null, 'unread' => isset($folder['unreadCount']) ? (int) $folder['unreadCount'] : null, 'messages' => isset($folder['messageCount']) ? (int) $folder['messageCount'] : null], array_values(array_filter($folders, 'is_array'))), 'count' => count($folders)];
    }
}
