<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\Concerns\ListsDriveFiles;

/**
 * Google Forms, newest first. Forms has no list call, so they are found as
 * Drive files of the form type.
 */
class GoogleFormsOptions extends HttpOptionsSource
{
    use ListsDriveFiles;

    private const string FORM_MIME_TYPE = 'application/vnd.google-apps.form';

    public function sources(): array
    {
        return ['google_forms.forms'];
    }

    protected function appName(): string
    {
        return 'Google Forms';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        return $this->driveFiles($query, self::FORM_MIME_TYPE);
    }
}
