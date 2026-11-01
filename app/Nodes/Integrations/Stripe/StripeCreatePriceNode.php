<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeCreatePriceNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_create_price';
    }

    public function name(): string
    {
        return 'Stripe: Create Price';
    }

    public function description(): string
    {
        return 'Creates a one-time or recurring price for a product.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['product_id', 'unit_amount', 'currency'],
            'properties' => [
                ...$this->credentialFields(),
                'product_id' => Field::dynamic('Product', 'stripe.products', null, 'prod_...'),
                'unit_amount' => Field::integer('Amount', 'In the smallest unit of the currency: 1999 is 19.99 USD.', null, 1),
                'currency' => Field::text('Currency', null, 'usd'),
                'interval' => Field::select('Repeats', ['' => 'Never (one-time)', 'day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly', 'year' => 'Yearly']),
                'nickname' => Field::advanced(Field::text('Nickname')),
                'metadata' => Field::advanced(Field::keyValue('Metadata', 'Your own notes on the record, such as an order id.')),
                'idempotency_key' => $this->idempotencyField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $interval = (string) ($config['interval'] ?? '');

        return $this->stripe($run, $config, 'POST', '/prices', [
            'product' => $this->stripeId($config, 'product_id', 'prod', 'product id'),
            'unit_amount' => $this->amount($config, 'unit_amount', 'The amount'),
            'currency' => $this->currency($config),
            'recurring' => $interval !== '' ? ['interval' => $interval] : null,
            ...$this->filled($config, ['nickname']),
            'metadata' => $this->metadata($config),
        ], $this->idempotencyKey($config));
    }
}
