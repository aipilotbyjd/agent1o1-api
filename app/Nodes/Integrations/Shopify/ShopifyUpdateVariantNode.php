<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ShopifyUpdateVariantNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_update_variant';
    }

    public function name(): string
    {
        return 'Shopify: Update Variant';
    }

    public function description(): string
    {
        return 'Changes the price, compare-at price, SKU or barcode of one variant.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['variant_id'],
            'properties' => [
                ...$this->credentialFields(),
                'variant_id' => Field::text('Variant ID', 'Found in the product\'s variants.', '808950810'),
                'price' => Field::text('Price', null, '19.99'),
                'compare_at_price' => Field::text('Compare-at price', 'The crossed-out price. Leave empty to keep it.'),
                'sku' => Field::text('SKU'),
                'barcode' => Field::advanced(Field::text('Barcode')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->shopifyId($config, 'variant_id', 'variant id');
        $changes = [
            ...$this->filled($config, ['sku', 'barcode']),
            'price' => filled($config['price'] ?? null) ? $this->price($config, 'price', 'The price') : null,
            'compare_at_price' => filled($config['compare_at_price'] ?? null) ? $this->price($config, 'compare_at_price', 'The compare-at price') : null,
        ];

        if (array_filter($changes, fn (mixed $value): bool => $value !== null) === []) {
            throw new InvalidArgumentException('Fill in something to change.');
        }

        return (array) ($this->shopify($run, $config, 'PUT', "/variants/{$id}.json", ['variant' => ['id' => (int) $id, ...$changes]])['variant'] ?? []);
    }
}
