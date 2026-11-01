<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ShopifyGetOrderNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_get_order';
    }

    public function name(): string
    {
        return 'Shopify: Get Order';
    }

    public function description(): string
    {
        return 'Reads one order with its items, customer, addresses and totals.';
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
        return (array) ($this->shopify($run, $config, 'GET', "/orders/{$this->shopifyId($config, 'order_id', 'order id')}.json")['order'] ?? []);
    }
}
