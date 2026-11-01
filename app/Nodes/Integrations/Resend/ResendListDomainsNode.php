<?php

namespace App\Nodes\Integrations\Resend;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ResendListDomainsNode extends AbstractResendNode
{
    public function type(): string
    {
        return 'resend_list_domains';
    }

    public function name(): string
    {
        return 'Resend: List Domains';
    }

    public function description(): string
    {
        return 'Lists the sending domains and whether each is verified.';
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
        $found = (array) $this->resend($run, $config, 'GET', '/domains');
        $domains = array_map(fn (array $domain): array => ['id' => $domain['id'] ?? null, 'name' => $domain['name'] ?? null, 'status' => $domain['status'] ?? null, 'region' => $domain['region'] ?? null, 'created_at' => $domain['created_at'] ?? null], array_values(array_filter((array) ($found['data'] ?? []), 'is_array')));

        return ['domains' => $domains, 'count' => count($domains)];
    }
}
