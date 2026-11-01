<?php

namespace App\Nodes\Integrations\GoogleDrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleDriveCopyFileNode extends AbstractGoogleDriveNode
{
    public function type(): string
    {
        return 'google_drive_copy_file';
    }

    public function name(): string
    {
        return 'Google Drive: Copy File';
    }

    public function description(): string
    {
        return 'Makes a copy of a file, for example to fill in a template.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['file_id'],
            'properties' => [
                ...$this->credentialFields(),
                'file_id' => Field::dynamic('File', 'google_drive.files', 'Pick the file to copy, or enter a Drive file ID.', '1AbCdEfGhIjKlMnOp'),
                'name' => Field::text('Name of the copy', 'Leave empty to use "Copy of ..." and the original name.', 'Contract - Acme'),
                'parent_id' => Field::dynamic('Folder for the copy', 'google_drive.folders', 'Leave empty to keep it next to the original.', '1AbCdEfGhIjKlMnOp'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $parent = trim((string) ($config['parent_id'] ?? ''));
        $name = trim((string) ($config['name'] ?? ''));

        return $this->post(
            $run,
            '/files/'.rawurlencode((string) $config['file_id']).'/copy?supportsAllDrives=true&fields='.rawurlencode(self::FILE_FIELDS),
            $config,
            array_filter([
                'name' => $name !== '' ? $name : null,
                'parents' => $parent !== '' ? [$parent] : null,
            ]),
        );
    }
}
