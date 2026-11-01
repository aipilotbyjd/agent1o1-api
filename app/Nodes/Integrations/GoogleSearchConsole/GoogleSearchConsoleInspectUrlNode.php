<?php

namespace App\Nodes\Integrations\GoogleSearchConsole;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleSearchConsoleInspectUrlNode extends AbstractGoogleSearchConsoleNode
{
    public function type(): string
    {
        return 'google_search_console_inspect_url';
    }

    public function name(): string
    {
        return 'Google Search Console: Inspect URL';
    }

    public function description(): string
    {
        return 'Tells whether Google has indexed a page, and when it was last crawled.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['site_url', 'inspection_url'],
            'properties' => [
                ...$this->credentialFields(),
                'site_url' => $this->siteField(),
                'inspection_url' => Field::text('Page URL', 'A full URL on the site above.', 'https://example.com/blog/post'),
            ],
        ];
    }

    protected function baseUrl(): string
    {
        return 'https://searchconsole.googleapis.com/v1';
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->post($run, '/urlInspection/index:inspect', $config, [
            'inspectionUrl' => $config['inspection_url'],
            'siteUrl' => $config['site_url'],
        ]);

        $index = (array) ($response['inspectionResult']['indexStatusResult'] ?? []);

        return [
            'verdict' => $index['verdict'] ?? null,
            'coverage' => $index['coverageState'] ?? null,
            'indexing_state' => $index['indexingState'] ?? null,
            'robots_txt_state' => $index['robotsTxtState'] ?? null,
            'last_crawl_time' => $index['lastCrawlTime'] ?? null,
            'google_canonical' => $index['googleCanonical'] ?? null,
            'user_canonical' => $index['userCanonical'] ?? null,
            'crawled_as' => $index['crawledAs'] ?? null,
            'report_url' => $response['inspectionResult']['inspectionResultLink'] ?? null,
        ];
    }
}
