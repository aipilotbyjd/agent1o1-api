<?php

namespace App\Nodes\Integrations\GoogleSearchConsole;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class GoogleSearchConsoleListSitemapsNode extends AbstractGoogleSearchConsoleNode
{
    public function type(): string
    {
        return 'google_search_console_list_sitemaps';
    }

    public function name(): string
    {
        return 'Google Search Console: List Sitemaps';
    }

    public function description(): string
    {
        return 'Lists a site\'s sitemaps, when they were last read and whether they have errors.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['site_url'],
            'properties' => [
                ...$this->credentialFields(),
                'site_url' => $this->siteField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->get($run, $this->sitePath($config).'/sitemaps', $config);

        return ['sitemaps' => array_map(fn (array $sitemap): array => [
            'path' => $sitemap['path'] ?? null,
            'last_submitted' => $sitemap['lastSubmitted'] ?? null,
            'last_downloaded' => $sitemap['lastDownloaded'] ?? null,
            'is_pending' => (bool) ($sitemap['isPending'] ?? false),
            'errors' => (int) ($sitemap['errors'] ?? 0),
            'warnings' => (int) ($sitemap['warnings'] ?? 0),
        ], (array) ($response['sitemap'] ?? []))];
    }
}
