<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeCreateSubscriptionNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_create_subscription';
    }

    public function name(): string
    {
        return 'Stripe: Create Subscription';
    }

    public function description(): string
    {
        return 'Subscribes a customer to a price.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['customer_id', 'price_id'],
            'properties' => [
                ...$this->credentialFields(),
                'customer_id' => Field::dynamic('Customer', 'stripe.customers', null, 'cus_...'),
                'price_id' => Field::dynamic('Price', 'stripe.prices', null, 'price_...'),
                'quantity' => Field::integer('Quantity', null, 1, 1),
                'trial_days' => Field::integer('Trial days', null, null, 1, 730),
                'default_payment_method' => Field::advanced(Field::text('Payment method', null, 'pm_...')),
                'metadata' => Field::advanced(Field::keyValue('Metadata', 'Your own notes on the record, such as an order id.')),
                'idempotency_key' => $this->idempotencyField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->stripe($run, $config, 'POST', '/subscriptions', [
            'customer' => $this->stripeId($config, 'customer_id', 'cus', 'customer id'),
            'items' => [['price' => $this->stripeId($config, 'price_id', 'price', 'price id'), 'quantity' => max(1, (int) ($config['quantity'] ?? 1))]],
            'trial_period_days' => filled($config['trial_days'] ?? null) ? $this->amount($config, 'trial_days', 'The trial days') : null,
            'default_payment_method' => $this->optionalId($config, 'default_payment_method', 'pm', 'payment method id'),
            'metadata' => $this->metadata($config),
        ], $this->idempotencyKey($config));
    }
}
