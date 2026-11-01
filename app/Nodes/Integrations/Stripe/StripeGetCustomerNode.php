<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeGetCustomerNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_get_customer';
    }

    public function name(): string
    {
        return 'Stripe: Get Customer';
    }

    public function description(): string
    {
        return 'Reads one customer.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['customer_id'],
            'properties' => [
                ...$this->credentialFields(),
                'customer_id' => Field::dynamic('Customer', 'stripe.customers', null, 'cus_...'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->stripe($run, $config, 'GET', '/customers/'.$this->stripeId($config, 'customer_id', 'cus', 'customer id'));
    }
}
