<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ShopifyGetInventoryLevelsNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_get_inventory_levels';
    }

    public function name(): string
    {
        return 'Shopify: Get Inventory Levels';
    }

    public function description(): string
    {
        return 'Reads how many of an item are in stock, at one location or all of them.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['inventory_item_id'],
            'properties' => [
                ...$this->credentialFields(),
                'inventory_item_id' => Field::text('Inventory item ID', 'The variant\'s inventory_item_id.', '808950810'),
                'location_id' => Field::dynamic('Location', 'shopify.locations', null, '905684977'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $levels = array_values(array_filter((array) ($this->shopify($run, $config, 'GET', '/inventory_levels.json', query: [
            'inventory_item_ids' => $this->shopifyId($config, 'inventory_item_id', 'inventory item id'),
            'location_ids' => $this->optionalShopifyId($config, 'location_id', 'location id'),
        ])['inventory_levels'] ?? []), 'is_array'));

        return ['inventory_levels' => $levels, 'count' => count($levels)];
    }
}
