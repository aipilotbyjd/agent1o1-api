<?php

namespace App\Nodes\Integrations\GoogleDrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A file's text: Google Docs and Slides as plain text, Sheets as CSV, and
 * text files as they are. Binary files (PDFs, images, Office files) can't
 * be read as text and are refused.
 */
class GoogleDriveExportFileNode extends AbstractGoogleDriveNode
{
    private const array EXPORTS = [
        'application/vnd.google-apps.document' => 'text/plain',
        'application/vnd.google-apps.presentation' => 'text/plain',
        'application/vnd.google-apps.spreadsheet' => 'text/csv',
    ];

    private const array TEXT_TYPES = ['application/json', 'application/xml', 'application/x-yaml', 'application/yaml'];

    private const int MAX_CHARS = 200000;

    public function type(): string
    {
        return 'google_drive_export_file';
    }

    public function name(): string
    {
        return 'Google Drive: Read File Text';
    }

    public function description(): string
    {
        return "Reads a file's text: Google Docs and Slides as plain text, Sheets as CSV, text files as they are.";
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        $id = rawurlencode((string) $config['file_id']);
        $file = $this->get($run, "/files/{$id}", $config, ['fields' => 'id,name,mimeType,modifiedTime,webViewLink']);
        $mimeType = (string) ($file['mimeType'] ?? '');

        $content = match (true) {
            isset(self::EXPORTS[$mimeType]) => $this->getText($run, "/files/{$id}/export", $config, ['mimeType' => self::EXPORTS[$mimeType]]),
            Str::startsWith($mimeType, 'text/') || in_array($mimeType, self::TEXT_TYPES, true) => $this->getText($run, "/files/{$id}", $config, ['alt' => 'media']),
            default => throw new RuntimeException("Can't read [{$mimeType}] files as text."),
        };

        return [
            'id' => $file['id'] ?? $config['file_id'],
            'name' => $file['name'] ?? null,
            'mime_type' => $mimeType,
            'modified_time' => $file['modifiedTime'] ?? null,
            'url' => $file['webViewLink'] ?? null,
            'content' => Str::limit($content, self::MAX_CHARS),
        ];
    }
}
