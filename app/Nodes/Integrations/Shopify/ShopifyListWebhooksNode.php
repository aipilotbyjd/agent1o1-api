<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ShopifyListWebhooksNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_list_webhooks';
    }

    public function name(): string
    {
        return 'Shopify: List Webhooks';
    }

    public function description(): string
    {
        return 'Lists the webhooks this app has set up on the store.';
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
        $webhooks = array_values(array_filter((array) ($this->shopify($run, $config, 'GET', '/webhooks.json')['webhooks'] ?? []), 'is_array'));

        return ['webhooks' => $webhooks, 'count' => count($webhooks)];
    }
}
