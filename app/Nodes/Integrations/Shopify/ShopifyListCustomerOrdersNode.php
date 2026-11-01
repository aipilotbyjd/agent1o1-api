<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ShopifyListCustomerOrdersNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_list_customer_orders';
    }

    public function name(): string
    {
        return 'Shopify: List Customer Orders';
    }

    public function description(): string
    {
        return 'Lists a customer\'s orders, newest first.';
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
                'limit' => Field::integer('Max results', null, 25, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOutput($run, $config, "/customers/{$this->shopifyId($config, 'customer_id', 'customer id')}/orders.json", 'orders', ['status' => 'any']);
    }
}
