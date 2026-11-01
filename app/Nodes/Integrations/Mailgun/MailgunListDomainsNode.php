<?php

namespace App\Nodes\Integrations\Mailgun;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MailgunListDomainsNode extends AbstractMailgunNode
{
    public function type(): string
    {
        return 'mailgun_list_domains';
    }

    public function name(): string
    {
        return 'Mailgun: List Domains';
    }

    public function description(): string
    {
        return 'Lists the account\'s sending domains and their state.';
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
        $found = (array) $this->mailgun($run, $config, 'GET', '/v4/domains', query: ['limit' => 1000]);
        $domains = array_map(fn (array $domain): array => ['name' => $domain['name'] ?? null, 'state' => $domain['state'] ?? null, 'type' => $domain['type'] ?? null, 'disabled' => (bool) data_get($domain, 'is_disabled', false), 'created_at' => $domain['created_at'] ?? null], array_values(array_filter((array) ($found['items'] ?? []), 'is_array')));

        return ['domains' => $domains, 'count' => count($domains)];
    }
}
