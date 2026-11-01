<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ShopifySetInventoryLevelNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_set_inventory_level';
    }

    public function name(): string
    {
        return 'Shopify: Set Inventory Level';
    }

    public function description(): string
    {
        return 'Sets how many of an item are in stock at a location.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['inventory_item_id', 'location_id', 'available'],
            'properties' => [
                ...$this->credentialFields(),
                'inventory_item_id' => Field::text('Inventory item ID', 'The variant\'s inventory_item_id.', '808950810'),
                'location_id' => Field::dynamic('Location', 'shopify.locations', null, '905684977'),
                'available' => Field::integer('In stock', null, null, 0),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        if (! is_numeric($config['available'] ?? null) || (int) $config['available'] < 0) {
            throw new InvalidArgumentException('The stock is a whole number, zero or more.');
        }

        return (array) ($this->shopify($run, $config, 'POST', '/inventory_levels/set.json', [
            'location_id' => (int) $this->shopifyId($config, 'location_id', 'location id'),
            'inventory_item_id' => (int) $this->shopifyId($config, 'inventory_item_id', 'inventory item id'),
            'available' => (int) $config['available'],
        ])['inventory_level'] ?? []);
    }
}
