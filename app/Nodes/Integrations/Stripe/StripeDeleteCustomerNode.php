<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeDeleteCustomerNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_delete_customer';
    }

    public function name(): string
    {
        return 'Stripe: Delete Customer';
    }

    public function description(): string
    {
        return 'Deletes a customer and cancels their subscriptions. This can\'t be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $customer = $this->stripeId($config, 'customer_id', 'cus', 'customer id');
        $result = $this->stripe($run, $config, 'DELETE', "/customers/{$customer}");

        return ['deleted' => (bool) ($result['deleted'] ?? false), 'customer_id' => $customer];
    }
}
