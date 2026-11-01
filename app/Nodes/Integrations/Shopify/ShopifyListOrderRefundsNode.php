<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ShopifyListOrderRefundsNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_list_order_refunds';
    }

    public function name(): string
    {
        return 'Shopify: List Order Refunds';
    }

    public function description(): string
    {
        return 'Lists the refunds made on an order.';
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
        $refunds = (array) ($this->shopify($run, $config, 'GET', "/orders/{$this->shopifyId($config, 'order_id', 'order id')}/refunds.json")['refunds'] ?? []);

        return ['refunds' => $refunds, 'count' => count($refunds)];
    }
}
