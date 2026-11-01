<?php

namespace App\Nodes\Integrations\Shopify;

class ShopifyProductUpdatedNode extends ShopifyListProductsNode
{
    public function type(): string
    {
        return 'shopify_product_updated';
    }

    public function name(): string
    {
        return 'Shopify: Product Updated';
    }

    public function description(): string
    {
        return 'Lists products that changed lately. As a trigger it starts the flow each time a product changes, instantly.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->credentialFields(),
                ...$this->windowFields(),
            ],
        ];
    }

    public function webhookTopic(): ?string
    {
        return 'products/update';
    }

    protected function stamp(): string
    {
        return 'updated_at';
    }

    public function triggerName(): string
    {
        return 'Shopify: Product updated';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow each time a product changes (price, stock, description), with the product as it is now.';
    }
}
