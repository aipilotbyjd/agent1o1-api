<?php

namespace App\Nodes\Integrations\GoogleDrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleDriveDeleteFileNode extends AbstractGoogleDriveNode
{
    public function type(): string
    {
        return 'google_drive_delete_file';
    }

    public function name(): string
    {
        return 'Google Drive: Delete File';
    }

    public function description(): string
    {
        return 'Deletes a file from Google Drive.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['file_id'],
            'properties' => [
                ...$this->credentialFields(),
                'file_id' => Field::dynamic('File', 'google_drive.files', 'Pick a file, or enter a Drive file ID.', '1AbCdEfGhIjKlMnOp'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->delete($run, "/files/{$config['file_id']}", $config);
    }
}
