<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ShopifyCreateOrderNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_create_order';
    }

    public function name(): string
    {
        return 'Shopify: Create Order';
    }

    public function description(): string
    {
        return 'Creates an order from variants (or custom items), for a customer or an email address.';
    }

    public function effect(array $config): ActionEffect
    {
        return filter_var($config['send_receipt'] ?? false, FILTER_VALIDATE_BOOLEAN) ? ActionEffect::External : ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['line_items'],
            'properties' => [
                ...$this->credentialFields(),
                'line_items' => Field::json('Line items', 'A list of {"variant_id": 123, "quantity": 1}, or {"title": "Custom", "price": "9.99", "quantity": 1}.', 'array'),
                'customer_id' => Field::dynamic('Customer', 'shopify.customers', null, '207119551'),
                'email' => Field::text('Email', 'For an order without a customer.', 'ada@example.com'),
                'financial_status' => Field::select('Payment', ['pending' => 'Pending', 'paid' => 'Paid'], null, 'pending'),
                'send_receipt' => Field::boolean('Email the customer a receipt'),
                'note' => Field::advanced(Field::text('Note')),
                'tags' => Field::advanced(Field::list('Tags')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $status = (string) ($config['financial_status'] ?? 'pending');

        if (! in_array($status, ['pending', 'paid'], true)) {
            throw new InvalidArgumentException('The payment is pending or paid.');
        }

        $customer = $this->optionalShopifyId($config, 'customer_id', 'customer id');
        $order = $this->shopify($run, $config, 'POST', '/orders.json', ['order' => [
            'line_items' => $this->lineItems($config),
            'customer' => $customer !== null ? ['id' => (int) $customer] : null,
            'email' => $customer === null && filled($config['email'] ?? null) ? trim((string) $config['email']) : null,
            'financial_status' => $status,
            'send_receipt' => filter_var($config['send_receipt'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
            ...$this->filled($config, ['note']),
            'tags' => $this->tags($config),
        ]]);

        return (array) ($order['order'] ?? []);
    }
}
