<?php

namespace App\Nodes\Integrations\GoogleSearchConsole;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Nodes\Integrations\Concerns\CallsGoogleJsonApi;
use App\Nodes\Support\Field;

/**
 * Shared request handling for the Google Search Console node family.
 */
abstract class AbstractGoogleSearchConsoleNode implements DeclaresEffect, NodeContract
{
    use CallsGoogleJsonApi;

    public function category(): string
    {
        return 'google_search_console';
    }

    protected function baseUrl(): string
    {
        return 'https://www.googleapis.com/webmasters/v3';
    }

    protected function appName(): string
    {
        return 'Google Search Console';
    }

    /**
     * @return array<string, mixed>
     */
    protected function siteField(): array
    {
        return Field::dynamic('Site', 'google_search_console.sites', 'Pick a site. A domain property looks like sc-domain:example.com.', 'https://example.com/');
    }

    /**
     * `/sites/{siteUrl}` with the URL encoded: it contains `:` and `/`.
     *
     * @param  array<string, mixed>  $config
     */
    protected function sitePath(array $config): string
    {
        return '/sites/'.rawurlencode((string) $config['site_url']);
    }
}
