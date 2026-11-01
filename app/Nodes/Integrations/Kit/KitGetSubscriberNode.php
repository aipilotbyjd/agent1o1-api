<?php

namespace App\Nodes\Integrations\Kit;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class KitGetSubscriberNode extends AbstractKitNode
{
    public function type(): string
    {
        return 'kit_get_subscriber';
    }

    public function name(): string
    {
        return 'Kit: Get Subscriber';
    }

    public function description(): string
    {
        return 'Gets a subscriber by email or id, with their state and custom fields.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['subscriber'],
            'properties' => [
                ...$this->credentialFields(),
                'subscriber' => Field::text('Subscriber', 'Their email address or Kit id.', 'ada@example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->subscriberId($run, $config);

        return $this->shapeSubscriber((array) data_get((array) $this->kit($run, $config, 'GET', "/subscribers/{$id}"), 'subscriber', []));
    }
}
