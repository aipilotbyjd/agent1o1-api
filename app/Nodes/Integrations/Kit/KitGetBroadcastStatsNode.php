<?php

namespace App\Nodes\Integrations\Kit;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class KitGetBroadcastStatsNode extends AbstractKitNode
{
    public function type(): string
    {
        return 'kit_get_broadcast_stats';
    }

    public function name(): string
    {
        return 'Kit: Get Broadcast Stats';
    }

    public function description(): string
    {
        return 'Gets how a sent broadcast did: recipients, opens, clicks and unsubscribes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['broadcast_id'],
            'properties' => [
                ...$this->credentialFields(),
                'broadcast_id' => Field::text('Broadcast id', null, '123'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'broadcast_id', 'The broadcast id');
        $stats = (array) data_get((array) $this->kit($run, $config, 'GET', "/broadcasts/{$id}/stats"), 'broadcast.stats', []);

        return ['broadcast_id' => $id, 'recipients' => $stats['recipients'] ?? null, 'open_rate' => $stats['open_rate'] ?? null, 'click_rate' => $stats['click_rate'] ?? null, 'unsubscribes' => $stats['unsubscribes'] ?? null, 'total_clicks' => $stats['total_clicks'] ?? null, 'emails_opened' => $stats['emails_opened'] ?? null];
    }
}
