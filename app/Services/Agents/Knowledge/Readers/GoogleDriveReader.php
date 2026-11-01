<?php

namespace App\Services\Agents\Knowledge\Readers;

use App\Enums\Agents\KnowledgeSourceType;
use App\Models\Agents\KnowledgeSource;
use App\Services\Agents\Knowledge\KnowledgeBatch;
use App\Services\Agents\Knowledge\KnowledgeDocumentData;
use Throwable;

/**
 * Google Docs, Sheets, Slides and text files, in one folder, or matching a
 * name, or all of Drive. Each sync reads files changed since the last one.
 */
class GoogleDriveReader implements KnowledgeReader
{
    use ReadsThroughNodes;

    public function type(): KnowledgeSourceType
    {
        return KnowledgeSourceType::GoogleDrive;
    }

    public function configRules(): array
    {
        return [
            'config.folder_id' => ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9_-]+$/'],
            'config.name_contains' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function read(KnowledgeSource $source, int $limit): KnowledgeBatch
    {
        $startedAt = now()->toRfc3339String();
        $query = collect([
            'trashed = false',
            "mimeType != 'application/vnd.google-apps.folder'",
            filled($source->config['folder_id'] ?? null) ? "'{$source->config['folder_id']}' in parents" : null,
            filled($source->config['name_contains'] ?? null) ? "name contains '".str_replace(['\\', "'"], ['', "\\'"], (string) $source->config['name_contains'])."'" : null,
            $source->sync_cursor !== null ? "modifiedTime > '{$source->sync_cursor}'" : null,
        ])->filter()->implode(' and ');

        $files = $this->node($source, 'google_drive_list_files', ['query' => $query, 'page_size' => $limit])['files'] ?? [];
        $documents = [];

        foreach ($files as $file) {
            try {
                $export = $this->node($source, 'google_drive_export_file', ['file_id' => $file['id']]);
            } catch (Throwable) {
                // PDFs, images and other binary files can't be read as text.
                continue;
            }

            $documents[] = new KnowledgeDocumentData((string) $file['id'], (string) ($export['name'] ?? $file['name'] ?? 'Untitled'), (string) $export['content'], $export['url'] ?? null);
        }

        return new KnowledgeBatch($documents, count($files) < $limit ? $startedAt : null);
    }
}
