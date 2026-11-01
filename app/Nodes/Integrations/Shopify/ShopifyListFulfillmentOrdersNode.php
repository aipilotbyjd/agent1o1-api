<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ShopifyListFulfillmentOrdersNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_list_fulfillment_orders';
    }

    public function name(): string
    {
        return 'Shopify: List Fulfillment Orders';
    }

    public function description(): string
    {
        return 'Lists what has to be shipped for an order, by location, with the items and their status.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['order_id'],
            'properties' => [
                ...$this->credentialFields(),
                'order_id' => Field::text('Order ID', 'The number in the order\'s admin link, not the #1001 name.', '450789469'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $orders = (array) ($this->shopify($run, $config, 'GET', "/orders/{$this->shopifyId($config, 'order_id', 'order id')}/fulfillment_orders.json")['fulfillment_orders'] ?? []);

        return ['fulfillment_orders' => $orders, 'count' => count($orders)];
    }
}
