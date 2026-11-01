<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ShopifyAdjustInventoryLevelNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_adjust_inventory_level';
    }

    public function name(): string
    {
        return 'Shopify: Adjust Inventory Level';
    }

    public function description(): string
    {
        return 'Adds to or takes from the stock of an item at a location (use a negative number to take).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['inventory_item_id', 'location_id', 'adjustment'],
            'properties' => [
                ...$this->credentialFields(),
                'inventory_item_id' => Field::text('Inventory item ID', 'The variant\'s inventory_item_id.', '808950810'),
                'location_id' => Field::dynamic('Location', 'shopify.locations', null, '905684977'),
                'adjustment' => Field::integer('Change', 'Plus to add stock, minus to remove it.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        if (! is_numeric($config['adjustment'] ?? null) || (int) $config['adjustment'] === 0) {
            throw new InvalidArgumentException('The change is a whole number other than zero.');
        }

        return (array) ($this->shopify($run, $config, 'POST', '/inventory_levels/adjust.json', [
            'location_id' => (int) $this->shopifyId($config, 'location_id', 'location id'),
            'inventory_item_id' => (int) $this->shopifyId($config, 'inventory_item_id', 'inventory item id'),
            'available_adjustment' => (int) $config['adjustment'],
        ])['inventory_level'] ?? []);
    }
}
