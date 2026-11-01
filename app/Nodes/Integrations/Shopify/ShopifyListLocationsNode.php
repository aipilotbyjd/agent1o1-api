<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ShopifyListLocationsNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_list_locations';
    }

    public function name(): string
    {
        return 'Shopify: List Locations';
    }

    public function description(): string
    {
        return 'Lists the places the store keeps stock.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->credentialFields(),

            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $locations = array_values(array_filter((array) ($this->shopify($run, $config, 'GET', '/locations.json')['locations'] ?? []), 'is_array'));

        return ['locations' => $locations, 'count' => count($locations)];
    }
}
