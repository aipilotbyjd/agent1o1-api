<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ShopifyCreateDraftOrderNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_create_draft_order';
    }

    public function name(): string
    {
        return 'Shopify: Create Draft Order';
    }

    public function description(): string
    {
        return 'Creates a draft order (a quote or invoice) that can be sent to the customer to pay.';
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
                'line_items' => Field::json('Line items', 'A list of {"variant_id": 123, "quantity": 1}, or {"title": "Custom", "price": "9.99", "quantity": 1}.', 'array'),
                'customer_id' => Field::dynamic('Customer', 'shopify.customers', null, '207119551'),
                'email' => Field::text('Email', 'For a draft without a customer.'),
                'note' => Field::advanced(Field::text('Note')),
                'tags' => Field::advanced(Field::list('Tags')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $customer = $this->optionalShopifyId($config, 'customer_id', 'customer id');

        return (array) ($this->shopify($run, $config, 'POST', '/draft_orders.json', ['draft_order' => [
            'line_items' => $this->lineItems($config),
            'customer' => $customer !== null ? ['id' => (int) $customer] : null,
            'email' => $customer === null && filled($config['email'] ?? null) ? trim((string) $config['email']) : null,
            ...$this->filled($config, ['note']),
            'tags' => $this->tags($config),
        ]])['draft_order'] ?? []);
    }
}
