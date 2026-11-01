<?php

namespace App\Nodes\Integrations\Postmark;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class PostmarkGetDeliveryStatsNode extends AbstractPostmarkNode
{
    public function type(): string
    {
        return 'postmark_get_delivery_stats';
    }

    public function name(): string
    {
        return 'Postmark: Get Bounce Counts';
    }

    public function description(): string
    {
        return 'Reads how many addresses are deactivated and the bounces by type.';
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
        $stats = (array) $this->postmark($run, $config, 'GET', '/deliverystats');

        return ['inactive' => $stats['InactiveMails'] ?? 0, 'bounces' => array_values(array_map(fn (array $bounce): array => ['type' => $bounce['Type'] ?? $bounce['Name'] ?? null, 'name' => $bounce['Name'] ?? null, 'count' => $bounce['Count'] ?? 0], array_filter((array) ($stats['Bounces'] ?? []), 'is_array')))];
    }
}
