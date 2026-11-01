<?php

namespace App\Nodes\Integrations\Kit;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class KitDeleteBroadcastNode extends AbstractKitNode
{
    public function type(): string
    {
        return 'kit_delete_broadcast';
    }

    public function name(): string
    {
        return 'Kit: Delete Broadcast';
    }

    public function description(): string
    {
        return 'Deletes a broadcast that has not been sent.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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

        $this->kit($run, $config, 'DELETE', "/broadcasts/{$id}");

        return ['deleted' => true, 'broadcast_id' => $id];
    }
}
