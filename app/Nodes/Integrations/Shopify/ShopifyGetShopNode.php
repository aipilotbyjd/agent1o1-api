<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ShopifyGetShopNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_get_shop';
    }

    public function name(): string
    {
        return 'Shopify: Get Shop';
    }

    public function description(): string
    {
        return 'Reads the store\'s name, currency, plan and time zone.';
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
        return (array) ($this->shopify($run, $config, 'GET', '/shop.json')['shop'] ?? []);
    }
}
