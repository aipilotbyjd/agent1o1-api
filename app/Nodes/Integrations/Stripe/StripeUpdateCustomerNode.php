<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeUpdateCustomerNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_update_customer';
    }

    public function name(): string
    {
        return 'Stripe: Update Customer';
    }

    public function description(): string
    {
        return 'Changes the fields you fill in on a customer and leaves the rest as they are.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['customer_id'],
            'properties' => [
                ...$this->credentialFields(),
                'customer_id' => Field::dynamic('Customer', 'stripe.customers', null, 'cus_...'),
                'email' => Field::text('Email'),
                'name' => Field::text('Name'),
                'phone' => Field::advanced(Field::text('Phone')),
                'description' => Field::advanced(Field::text('Description')),
                'metadata' => Field::advanced(Field::keyValue('Metadata', 'Your own notes on the record, such as an order id.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->stripe($run, $config, 'POST', '/customers/'.$this->stripeId($config, 'customer_id', 'cus', 'customer id'), [
            ...$this->filled($config, ['email', 'name', 'phone', 'description']),
            'metadata' => $this->metadata($config),
        ]);
    }
}
