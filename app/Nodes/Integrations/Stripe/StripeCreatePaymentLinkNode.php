<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class StripeCreatePaymentLinkNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_create_payment_link';
    }

    public function name(): string
    {
        return 'Stripe: Create Payment Link';
    }

    public function description(): string
    {
        return 'Makes a link that can be shared to take payment for one or more prices.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['line_items'],
            'properties' => [
                ...$this->credentialFields(),
                'line_items' => Field::json('Line items', 'A list of {"price": "price_...", "quantity": 1}.', 'array'),
                'metadata' => Field::advanced(Field::keyValue('Metadata', 'Your own notes on the record, such as an order id.')),
                'idempotency_key' => $this->idempotencyField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->stripe($run, $config, 'POST', '/payment_links', [
            'line_items' => $this->lineItems($config),
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
