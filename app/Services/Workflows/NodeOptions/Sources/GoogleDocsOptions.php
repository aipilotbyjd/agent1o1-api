<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\Concerns\ListsDriveFiles;

/**
 * Google Docs documents, newest first.
 */
class GoogleDocsOptions extends HttpOptionsSource
{
    use ListsDriveFiles;

    private const string DOCUMENT_MIME_TYPE = 'application/vnd.google-apps.document';

    public function sources(): array
    {
        return ['google_docs.documents'];
    }

    protected function appName(): string
    {
        return 'Google Docs';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        return $this->driveFiles($query, self::DOCUMENT_MIME_TYPE);
    }
}
