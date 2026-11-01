<?php

namespace App\Nodes\Integrations\Shopify;

use App\Nodes\Support\Field;

class ShopifyListProductsNode extends AbstractShopifyTriggerNode
{
    public function type(): string
    {
        return 'shopify_list_products';
    }

    public function name(): string
    {
        return 'Shopify: List Products';
    }

    public function description(): string
    {
        return 'Lists products with their variants and images, newest first. As a trigger it starts the flow once for each new product, instantly.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->credentialFields(),
                'status' => Field::select('Status', ['' => 'Any', 'active' => 'Active', 'draft' => 'Draft', 'archived' => 'Archived']),
                'vendor' => Field::text('Vendor'),
                'product_type' => Field::text('Product type'),
                ...$this->windowFields(),
            ],
        ];
    }

    protected function path(): string
    {
        return '/products.json';
    }

    protected function itemsKey(): string
    {
        return 'products';
    }

    public function webhookTopic(): ?string
    {
        return 'products/create';
    }

    protected function filters(array $config): array
    {
        return [
            'status' => filled($config['status'] ?? null) ? (string) $config['status'] : null,
            'vendor' => filled($config['vendor'] ?? null) ? (string) $config['vendor'] : null,
            'product_type' => filled($config['product_type'] ?? null) ? (string) $config['product_type'] : null,
        ];
    }

    public function triggerName(): string
    {
        return 'Shopify: New product';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow once for each new product, with its variants and images.';
    }
}
