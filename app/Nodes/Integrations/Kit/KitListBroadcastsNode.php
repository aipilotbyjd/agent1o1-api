<?php

namespace App\Nodes\Integrations\Kit;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class KitListBroadcastsNode extends AbstractKitNode
{
    public function type(): string
    {
        return 'kit_list_broadcasts';
    }

    public function name(): string
    {
        return 'Kit: List Broadcasts';
    }

    public function description(): string
    {
        return 'Lists broadcasts (one-off emails), newest first.';
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
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $broadcasts = $this->listAll($run, $config, '/broadcasts', 'broadcasts', [], $this->limit($config, 50));

        return ['broadcasts' => array_map($this->shapeBroadcast(...), $broadcasts), 'count' => count($broadcasts)];
    }
}
