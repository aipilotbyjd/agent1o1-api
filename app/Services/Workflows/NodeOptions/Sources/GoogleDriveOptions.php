<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\Concerns\ListsDriveFiles;

/**
 * Any Drive file, or any folder, newest first.
 */
class GoogleDriveOptions extends HttpOptionsSource
{
    use ListsDriveFiles;

    private const string FOLDER_MIME_TYPE = 'application/vnd.google-apps.folder';

    public function sources(): array
    {
        return ['google_drive.files', 'google_drive.folders'];
    }

    protected function appName(): string
    {
        return 'Google Drive';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        return $this->driveFiles($query, $source === 'google_drive.folders' ? self::FOLDER_MIME_TYPE : null);
    }
}
