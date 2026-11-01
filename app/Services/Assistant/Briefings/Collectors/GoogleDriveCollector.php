<?php

namespace App\Services\Assistant\Briefings\Collectors;

use App\Models\Assistant\Assistant;
use App\Models\Connectors\ConnectorCredential;
use App\Services\Assistant\Briefings\NodeReader;
use App\Services\Assistant\Briefings\SourceCollector;
use Carbon\CarbonInterface;

/**
 * Files changed since the last report.
 */
class GoogleDriveCollector implements SourceCollector
{
    public function __construct(private readonly NodeReader $reader) {}

    public function source(): string
    {
        return 'google_drive';
    }

    public function collect(Assistant $assistant, ConnectorCredential $credential, CarbonInterface $since, int $limit): array
    {
        $result = $this->reader->read('google_drive_list_files', $assistant, $credential, [
            'query' => "modifiedTime > '{$since->copy()->utc()->format('Y-m-d\TH:i:s')}' and trashed = false",
            'page_size' => $limit,
        ]);

        return collect($result['files'] ?? [])
            ->map(fn (array $file): array => [
                'title' => (string) ($file['name'] ?? 'Untitled file'),
                'detail' => 'Changed file ('.($file['mimeType'] ?? 'file').')',
                'at' => $file['modifiedTime'] ?? null,
                'url' => isset($file['id']) ? "https://drive.google.com/open?id={$file['id']}" : null,
                'people' => [],
            ])
            ->values()
            ->all();
    }
}
