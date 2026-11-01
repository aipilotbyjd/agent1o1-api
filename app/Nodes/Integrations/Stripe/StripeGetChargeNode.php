<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeGetChargeNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_get_charge';
    }

    public function name(): string
    {
        return 'Stripe: Get Charge';
    }

    public function description(): string
    {
        return 'Reads one charge.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['charge_id'],
            'properties' => [
                ...$this->credentialFields(),
                'charge_id' => Field::text('Charge ID', null, 'ch_...'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->stripe($run, $config, 'GET', '/charges/'.$this->stripeId($config, 'charge_id', 'ch', 'charge id'));
    }
}
