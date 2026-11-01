<?php

namespace App\Nodes\Integrations\Shopify;

use App\Nodes\Support\Field;

class ShopifyListOrdersNode extends AbstractShopifyTriggerNode
{
    public function type(): string
    {
        return 'shopify_list_orders';
    }

    public function name(): string
    {
        return 'Shopify: List Orders';
    }

    public function description(): string
    {
        return 'Lists orders, newest first, with their line items, customer and addresses. As a trigger it starts the flow once for each new order, instantly.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->credentialFields(),
                'status' => Field::select('Status', ['any' => 'Any', 'open' => 'Open', 'closed' => 'Closed', 'cancelled' => 'Cancelled'], null, 'any'),
                'financial_status' => Field::select('Payment', ['' => 'Any', 'pending' => 'Pending', 'authorized' => 'Authorized', 'partially_paid' => 'Partially paid', 'paid' => 'Paid', 'partially_refunded' => 'Partially refunded', 'refunded' => 'Refunded', 'voided' => 'Voided']),
                'fulfillment_status' => Field::select('Fulfillment', ['' => 'Any', 'unfulfilled' => 'Not fulfilled', 'partial' => 'Partly fulfilled', 'shipped' => 'Fulfilled']),
                ...$this->windowFields(),
            ],
        ];
    }

    protected function path(): string
    {
        return '/orders.json';
    }

    protected function itemsKey(): string
    {
        return 'orders';
    }

    public function webhookTopic(): ?string
    {
        return 'orders/create';
    }

    protected function filters(array $config): array
    {
        return [
            'status' => $config['status'] ?? 'any',
            'financial_status' => filled($config['financial_status'] ?? null) ? (string) $config['financial_status'] : null,
            'fulfillment_status' => filled($config['fulfillment_status'] ?? null) ? (string) $config['fulfillment_status'] : null,
        ];
    }

    public function triggerName(): string
    {
        return 'Shopify: New order';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow once for each new order in the store, with its items, customer and totals.';
    }
}
