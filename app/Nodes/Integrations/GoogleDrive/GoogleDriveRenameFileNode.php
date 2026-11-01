<?php

namespace App\Nodes\Integrations\GoogleDrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleDriveRenameFileNode extends AbstractGoogleDriveNode
{
    public function type(): string
    {
        return 'google_drive_rename_file';
    }

    public function name(): string
    {
        return 'Google Drive: Rename File';
    }

    public function description(): string
    {
        return 'Changes the name and, optionally, the description of a file or folder.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['file_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'file_id' => Field::dynamic('File', 'google_drive.files', 'Pick a file, or enter a Drive file ID.', '1AbCdEfGhIjKlMnOp'),
                'name' => Field::text('New name', null, 'Final report'),
                'description' => Field::textarea('Description', 'Leave empty to keep the current description.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $description = trim((string) ($config['description'] ?? ''));

        return $this->patch(
            $run,
            '/files/'.rawurlencode((string) $config['file_id']).'?supportsAllDrives=true&fields='.rawurlencode(self::FILE_FIELDS),
            $config,
            array_filter(['name' => $config['name'], 'description' => $description !== '' ? $description : null]),
        );
    }
}
