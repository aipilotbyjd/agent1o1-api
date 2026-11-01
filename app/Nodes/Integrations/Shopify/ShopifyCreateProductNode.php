<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ShopifyCreateProductNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_create_product';
    }

    public function name(): string
    {
        return 'Shopify: Create Product';
    }

    public function description(): string
    {
        return 'Creates a product, with one variant or several.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title'],
            'properties' => [
                ...$this->credentialFields(),
                'title' => Field::text('Title', null, 'Cotton t-shirt'),
                'body_html' => Field::textarea('Description', 'HTML is allowed.'),
                'vendor' => Field::text('Vendor'),
                'product_type' => Field::text('Product type'),
                'status' => Field::select('Status', ['' => 'Leave as it is', 'active' => 'Active', 'draft' => 'Draft', 'archived' => 'Archived']),
                'tags' => Field::advanced(Field::list('Tags')),
                'price' => Field::text('Price', 'For a product with one variant.', '19.99'),
                'sku' => Field::text('SKU'),
                'variants' => Field::advanced(Field::json('Variants', 'A list of {"price": "19.99", "sku": "TS-S", "option1": "Small"}. Replaces the price and SKU above.', 'array')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $variants = array_values(array_filter($this->decoded($config['variants'] ?? null, 'The variants') ?? [], 'is_array'));

        if ($variants === [] && filled($config['price'] ?? null)) {
            $variants = [array_filter(['price' => $this->price($config, 'price', 'The price'), 'sku' => filled($config['sku'] ?? null) ? (string) $config['sku'] : null])];
        }

        foreach ($variants as $index => $variant) {
            $variants[$index] = isset($variant['price']) ? [...$variant, 'price' => $this->price($variant, 'price', 'The price')] : $variant;
        }

        return (array) ($this->shopify($run, $config, 'POST', '/products.json', ['product' => [
            ...$this->filled($config, ['title', 'body_html', 'vendor', 'product_type', 'status']),
            'title' => $this->requiredString($config, 'title', 'The title'),
            'tags' => $this->tags($config),
            'variants' => $variants !== [] ? $variants : null,
        ]])['product'] ?? []);
    }
}
