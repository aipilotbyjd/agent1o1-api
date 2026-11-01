<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeGetEventNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_get_event';
    }

    public function name(): string
    {
        return 'Stripe: Get Event';
    }

    public function description(): string
    {
        return 'Reads one event (Stripe keeps them for 30 days), with the object it is about.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['event_id'],
            'properties' => [
                ...$this->credentialFields(),
                'event_id' => Field::text('Event ID', null, 'evt_...'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeEvent($this->stripe($run, $config, 'GET', '/events/'.$this->stripeId($config, 'event_id', 'evt', 'event id')));
    }
}
