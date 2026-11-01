<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ShopifyDeleteCustomerNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_delete_customer';
    }

    public function name(): string
    {
        return 'Shopify: Delete Customer';
    }

    public function description(): string
    {
        return 'Deletes a customer. Shopify refuses if they have orders. This can\'t be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $id = $this->shopifyId($config, 'customer_id', 'customer id');
        $this->shopify($run, $config, 'DELETE', "/customers/{$id}.json");

        return ['deleted' => true, 'customer_id' => $id];
    }
}
