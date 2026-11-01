<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use RuntimeException;

class ShopifyCreateFulfillmentNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_create_fulfillment';
    }

    public function name(): string
    {
        return 'Shopify: Create Fulfillment';
    }

    public function description(): string
    {
        return 'Marks everything still to ship on an order as shipped, with a tracking number, and can tell the customer.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['order_id'],
            'properties' => [
                ...$this->credentialFields(),
                'order_id' => Field::text('Order ID', 'The number in the order\'s admin link, not the #1001 name.', '450789469'),
                'tracking_number' => Field::text('Tracking number'),
                'tracking_company' => Field::text('Carrier', null, 'UPS'),
                'tracking_url' => Field::advanced(Field::text('Tracking link')),
                'notify_customer' => Field::boolean('Email the customer', null, true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->shopifyId($config, 'order_id', 'order id');
        $open = collect((array) ($this->shopify($run, $config, 'GET', "/orders/{$id}/fulfillment_orders.json")['fulfillment_orders'] ?? []))
            ->filter(fn (mixed $order): bool => is_array($order) && in_array($order['status'] ?? null, ['open', 'in_progress'], true))
            ->values();

        if ($open->isEmpty()) {
            throw new RuntimeException('Shopify error: nothing is left to ship on this order.');
        }

        $tracking = array_filter(['number' => $config['tracking_number'] ?? null, 'company' => $config['tracking_company'] ?? null, 'url' => $config['tracking_url'] ?? null], fn (mixed $value): bool => filled($value));
        $fulfillment = $this->shopify($run, $config, 'POST', '/fulfillments.json', ['fulfillment' => [
            'line_items_by_fulfillment_order' => $open->map(fn (array $order): array => ['fulfillment_order_id' => $order['id']])->all(),
            'tracking_info' => $tracking !== [] ? array_map('strval', $tracking) : null,
            'notify_customer' => filter_var($config['notify_customer'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ]]);

        return (array) ($fulfillment['fulfillment'] ?? []);
    }
}
