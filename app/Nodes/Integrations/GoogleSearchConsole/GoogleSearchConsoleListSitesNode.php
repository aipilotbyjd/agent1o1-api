<?php

namespace App\Nodes\Integrations\GoogleSearchConsole;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class GoogleSearchConsoleListSitesNode extends AbstractGoogleSearchConsoleNode
{
    public function type(): string
    {
        return 'google_search_console_list_sites';
    }

    public function name(): string
    {
        return 'Google Search Console: List Sites';
    }

    public function description(): string
    {
        return 'Lists the sites the account can see, and its permission level on each.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->get($run, '/sites', $config);

        return ['sites' => array_map(fn (array $site): array => [
            'site_url' => $site['siteUrl'] ?? null,
            'permission_level' => $site['permissionLevel'] ?? null,
        ], (array) ($response['siteEntry'] ?? []))];
    }
}
