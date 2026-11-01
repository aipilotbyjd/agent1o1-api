<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ShopifyGetCustomerNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_get_customer';
    }

    public function name(): string
    {
        return 'Shopify: Get Customer';
    }

    public function description(): string
    {
        return 'Reads one customer with their addresses, tags and order totals.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['customer_id'],
            'properties' => [
                ...$this->credentialFields(),
                'customer_id' => Field::dynamic('Customer', 'shopify.customers', null, '207119551'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return (array) ($this->shopify($run, $config, 'GET', "/customers/{$this->shopifyId($config, 'customer_id', 'customer id')}.json")['customer'] ?? []);
    }
}
