<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ShopifyGetProductNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_get_product';
    }

    public function name(): string
    {
        return 'Shopify: Get Product';
    }

    public function description(): string
    {
        return 'Reads one product with its variants, options and images.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['product_id'],
            'properties' => [
                ...$this->credentialFields(),
                'product_id' => Field::dynamic('Product', 'shopify.products', null, '632910392'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return (array) ($this->shopify($run, $config, 'GET', "/products/{$this->shopifyId($config, 'product_id', 'product id')}.json")['product'] ?? []);
    }
}
