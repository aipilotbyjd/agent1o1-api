<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class StripeCreateCheckoutSessionNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_create_checkout_session';
    }

    public function name(): string
    {
        return 'Stripe: Create Checkout Session';
    }

    public function description(): string
    {
        return 'Makes a Stripe Checkout page for a payment or subscription and returns its link.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['line_items', 'success_url'],
            'properties' => [
                ...$this->credentialFields(),
                'mode' => Field::select('Mode', ['payment' => 'One-time payment', 'subscription' => 'Subscription'], null, 'payment'),
                'line_items' => Field::json('Line items', 'A list of {"price": "price_...", "quantity": 1}.', 'array'),
                'success_url' => Field::text('Success link', 'Where the customer goes after paying.', 'https://example.com/thanks'),
                'cancel_url' => Field::text('Cancel link', null, 'https://example.com/cart'),
                'customer_id' => Field::dynamic('Customer', 'stripe.customers', null, 'cus_...'),
                'customer_email' => Field::text('Customer email', 'Fills in the email when there is no customer.'),
                'metadata' => Field::advanced(Field::keyValue('Metadata', 'Your own notes on the record, such as an order id.')),
                'idempotency_key' => $this->idempotencyField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $mode = (string) ($config['mode'] ?? 'payment');

        if (! in_array($mode, ['payment', 'subscription'], true)) {
            throw new InvalidArgumentException('The mode is payment or subscription.');
        }

        return $this->stripe($run, $config, 'POST', '/checkout/sessions', [
            'mode' => $mode,
            'line_items' => $this->lineItems($config),
            'success_url' => $this->requiredString($config, 'success_url', 'The success link'),
            ...$this->filled($config, ['cancel_url']),
            'customer' => $this->optionalId($config, 'customer_id', 'cus', 'customer id'),
            'customer_email' => blank($config['customer_id'] ?? null) ? (filled($config['customer_email'] ?? null) ? (string) $config['customer_email'] : null) : null,
            'metadata' => $this->metadata($config),
        ], $this->idempotencyKey($config));
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<array{price: string, quantity: int}>
     */
    private function lineItems(array $config): array
    {
        $items = $this->decoded($config['line_items'] ?? null, 'The line items') ?? [];
        $lines = [];

        foreach (array_values(array_filter($items, 'is_array')) as $item) {
            $lines[] = ['price' => $this->stripeId($item, 'price', 'price', 'price id'), 'quantity' => max(1, (int) ($item['quantity'] ?? 1))];
        }

        return $lines !== [] ? $lines : throw new InvalidArgumentException('Add at least one line item with a price.');
    }
}
