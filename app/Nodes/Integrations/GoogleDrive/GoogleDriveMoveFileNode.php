<?php

namespace App\Nodes\Integrations\GoogleDrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleDriveMoveFileNode extends AbstractGoogleDriveNode
{
    public function type(): string
    {
        return 'google_drive_move_file';
    }

    public function name(): string
    {
        return 'Google Drive: Move File';
    }

    public function description(): string
    {
        return 'Moves a file or folder into another folder.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['file_id', 'destination_id'],
            'properties' => [
                ...$this->credentialFields(),
                'file_id' => Field::dynamic('File', 'google_drive.files', 'Pick the file to move, or enter a Drive file ID.', '1AbCdEfGhIjKlMnOp'),
                'destination_id' => Field::dynamic('Move to folder', 'google_drive.folders', 'The folder it ends up in.', '1AbCdEfGhIjKlMnOp'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = rawurlencode((string) $config['file_id']);
        $current = $this->get($run, "/files/{$id}", $config, ['fields' => 'parents', 'supportsAllDrives' => 'true']);

        $query = http_build_query(array_filter([
            'addParents' => $config['destination_id'],
            'removeParents' => implode(',', (array) ($current['parents'] ?? [])) ?: null,
            'supportsAllDrives' => 'true',
            'fields' => self::FILE_FIELDS,
        ]));

        return $this->patch($run, "/files/{$id}?{$query}", $config);
    }
}
