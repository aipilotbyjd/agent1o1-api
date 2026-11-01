<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ShopifyDeleteProductNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_delete_product';
    }

    public function name(): string
    {
        return 'Shopify: Delete Product';
    }

    public function description(): string
    {
        return 'Deletes a product and its variants. This can\'t be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $id = $this->shopifyId($config, 'product_id', 'product id');
        $this->shopify($run, $config, 'DELETE', "/products/{$id}.json");

        return ['deleted' => true, 'product_id' => $id];
    }
}
