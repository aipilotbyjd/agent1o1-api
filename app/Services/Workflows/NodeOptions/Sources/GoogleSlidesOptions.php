<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\Concerns\ListsDriveFiles;

/**
 * Google Slides presentations, newest first.
 */
class GoogleSlidesOptions extends HttpOptionsSource
{
    use ListsDriveFiles;

    private const string PRESENTATION_MIME_TYPE = 'application/vnd.google-apps.presentation';

    public function sources(): array
    {
        return ['google_slides.presentations'];
    }

    protected function appName(): string
    {
        return 'Google Slides';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        return $this->driveFiles($query, self::PRESENTATION_MIME_TYPE);
    }
}
