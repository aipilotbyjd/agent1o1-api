<?php

namespace App\Services\Workflows\NodeOptions\Sources\Concerns;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Drive's file list, newest first, searched by name server-side: what the
 * Drive, Sheets and Docs pickers all browse (a spreadsheet or document is a
 * Drive file of a given type).
 */
trait ListsDriveFiles
{
    private const string DRIVE_FILES_URL = 'https://www.googleapis.com/drive/v3/files';

    private const int DRIVE_PAGE_SIZE = 50;

    protected function driveFiles(NodeOptionsQuery $query, ?string $mimeType = null): NodeOptionsPage
    {
        $conditions = ['trashed = false'];

        if ($mimeType !== null) {
            $conditions[] = "mimeType = '{$mimeType}'";
        }

        if ($query->search !== null) {
            // Drive's query language quotes with ' and escapes with \.
            $conditions[] = "name contains '".addcslashes($query->search, "\\'")."'";
        }

        $body = $this->getJson($query, self::DRIVE_FILES_URL, [
            'q' => implode(' and ', $conditions),
            'orderBy' => 'modifiedTime desc',
            'pageSize' => self::DRIVE_PAGE_SIZE,
            'pageToken' => $query->cursor,
            'fields' => 'nextPageToken, files(id, name, mimeType, modifiedTime)',
            'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true',
        ]);

        $options = collect($body['files'] ?? [])
            ->filter(fn (mixed $file): bool => is_array($file) && isset($file['id']))
            ->map(fn (array $file): NodeOption => new NodeOption(
                (string) $file['id'],
                $file['name'] ?? null,
                $this->describeDriveFile($file, withType: $mimeType === null),
            ));

        return new NodeOptionsPage($options, $body['nextPageToken'] ?? null);
    }

    /**
     * "Spreadsheet · Modified Sep 1, 2026".
     *
     * @param  array<string, mixed>  $file
     */
    private function describeDriveFile(array $file, bool $withType): ?string
    {
        $parts = [];

        if ($withType && isset($file['mimeType'])) {
            $parts[] = Str::of((string) $file['mimeType'])->afterLast('/')->afterLast('.')->headline()->toString();
        }

        if (isset($file['modifiedTime'])) {
            $parts[] = 'Modified '.Carbon::parse((string) $file['modifiedTime'])->toFormattedDateString();
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
