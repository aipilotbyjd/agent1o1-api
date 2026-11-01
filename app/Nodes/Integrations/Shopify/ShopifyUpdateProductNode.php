<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ShopifyUpdateProductNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_update_product';
    }

    public function name(): string
    {
        return 'Shopify: Update Product';
    }

    public function description(): string
    {
        return 'Changes the fields you fill in on a product and leaves the rest as they are.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['product_id'],
            'properties' => [
                ...$this->credentialFields(),
                'product_id' => Field::dynamic('Product', 'shopify.products', null, '632910392'),
                'title' => Field::text('Title', null, 'Cotton t-shirt'),
                'body_html' => Field::textarea('Description', 'HTML is allowed.'),
                'vendor' => Field::text('Vendor'),
                'product_type' => Field::text('Product type'),
                'status' => Field::select('Status', ['' => 'Leave as it is', 'active' => 'Active', 'draft' => 'Draft', 'archived' => 'Archived']),
                'tags' => Field::advanced(Field::list('Tags')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->shopifyId($config, 'product_id', 'product id');

        return (array) ($this->shopify($run, $config, 'PUT', "/products/{$id}.json", ['product' => [
            ...$this->filled($config, ['title', 'body_html', 'vendor', 'product_type', 'status']),
            'id' => (int) $id,
            'tags' => $this->tags($config),
        ]])['product'] ?? []);
    }
}
