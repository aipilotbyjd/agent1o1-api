<?php

namespace App\Nodes\Integrations\Resend;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ResendListBroadcastsNode extends AbstractResendNode
{
    public function type(): string
    {
        return 'resend_list_broadcasts';
    }

    public function name(): string
    {
        return 'Resend: List Broadcasts';
    }

    public function description(): string
    {
        return 'Lists the broadcasts with their status.';
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
        $found = (array) $this->resend($run, $config, 'GET', '/broadcasts');
        $broadcasts = array_map(fn (array $broadcast): array => ['id' => $broadcast['id'] ?? null, 'name' => $broadcast['name'] ?? null, 'audience_id' => $broadcast['audience_id'] ?? null, 'status' => $broadcast['status'] ?? null, 'created_at' => $broadcast['created_at'] ?? null, 'scheduled_at' => $broadcast['scheduled_at'] ?? null, 'sent_at' => $broadcast['sent_at'] ?? null], array_values(array_filter((array) ($found['data'] ?? []), 'is_array')));

        return ['broadcasts' => $broadcasts, 'count' => count($broadcasts)];
    }
}
