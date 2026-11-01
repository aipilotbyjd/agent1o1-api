<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ShopifyCloseOrderNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_close_order';
    }

    public function name(): string
    {
        return 'Shopify: Close Order';
    }

    public function description(): string
    {
        return 'Closes an order that is complete.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
        return (array) ($this->shopify($run, $config, 'POST', "/orders/{$this->shopifyId($config, 'order_id', 'order id')}/close.json", [])['order'] ?? []);
    }
}
