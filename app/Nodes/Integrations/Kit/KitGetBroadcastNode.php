<?php

namespace App\Nodes\Integrations\Kit;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class KitGetBroadcastNode extends AbstractKitNode
{
    public function type(): string
    {
        return 'kit_get_broadcast';
    }

    public function name(): string
    {
        return 'Kit: Get Broadcast';
    }

    public function description(): string
    {
        return 'Gets a broadcast, with its content and send time.';
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
                'broadcast_id' => Field::text('Broadcast id', 'From List Broadcasts or Create Broadcast.', '123'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'broadcast_id', 'The broadcast id');
        $broadcast = (array) data_get((array) $this->kit($run, $config, 'GET', "/broadcasts/{$id}"), 'broadcast', []);

        return [...$this->shapeBroadcast($broadcast), 'content' => $broadcast['content'] ?? null];
    }
}
