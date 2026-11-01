<?php

namespace App\Nodes\Integrations\GoogleDrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleDriveCreateFolderNode extends AbstractGoogleDriveNode
{
    public function type(): string
    {
        return 'google_drive_create_folder';
    }

    public function name(): string
    {
        return 'Google Drive: Create Folder';
    }

    public function description(): string
    {
        return 'Creates a folder, in My Drive or inside another folder.';
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
                'name' => Field::text('Folder name', null, 'Invoices 2026'),
                'parent_id' => Field::dynamic('Parent folder', 'google_drive.folders', 'Leave empty to create it in My Drive.', '1AbCdEfGhIjKlMnOp'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $parent = trim((string) ($config['parent_id'] ?? ''));

        return $this->post($run, '/files?supportsAllDrives=true&fields='.rawurlencode(self::FILE_FIELDS), $config, array_filter([
            'name' => $config['name'],
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => $parent !== '' ? [$parent] : null,
        ]));
    }
}
