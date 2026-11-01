<?php

namespace App\Nodes\Integrations\GoogleDrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleDriveUploadFileNode extends AbstractGoogleDriveNode
{
    public function type(): string
    {
        return 'google_drive_upload_file';
    }

    public function name(): string
    {
        return 'Google Drive: Upload File';
    }

    public function description(): string
    {
        return 'Creates a text file (a note, CSV, JSON or HTML page) in Drive.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'content'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('File name', 'Include the extension.', 'report.csv'),
                'content' => Field::textarea('File content', 'The text the file holds.'),
                'mime_type' => Field::select('File type', [
                    'text/plain' => 'Plain text',
                    'text/csv' => 'CSV',
                    'application/json' => 'JSON',
                    'text/html' => 'HTML',
                    'text/markdown' => 'Markdown',
                ], null, 'text/plain'),
                'parent_id' => Field::dynamic('Folder', 'google_drive.folders', 'Leave empty to save it in My Drive.', '1AbCdEfGhIjKlMnOp'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $parent = trim((string) ($config['parent_id'] ?? ''));
        $mimeType = (string) ($config['mime_type'] ?? 'text/plain');

        return $this->upload($run, $config, array_filter([
            'name' => $config['name'],
            'mimeType' => $mimeType,
            'parents' => $parent !== '' ? [$parent] : null,
        ]), (string) $config['content'], $mimeType);
    }
}
