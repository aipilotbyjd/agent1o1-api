<?php

namespace App\Nodes\Integrations\Kit;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class KitUnsubscribeSubscriberNode extends AbstractKitNode
{
    public function type(): string
    {
        return 'kit_unsubscribe_subscriber';
    }

    public function name(): string
    {
        return 'Kit: Unsubscribe Subscriber';
    }

    public function description(): string
    {
        return 'Unsubscribes a subscriber from all your emails.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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

        $this->kit($run, $config, 'POST', "/subscribers/{$id}/unsubscribe");

        return ['unsubscribed' => true, 'subscriber_id' => $id];
    }
}
