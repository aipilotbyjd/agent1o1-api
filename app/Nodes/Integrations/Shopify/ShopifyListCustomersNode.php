<?php

namespace App\Nodes\Integrations\Shopify;

class ShopifyListCustomersNode extends AbstractShopifyTriggerNode
{
    public function type(): string
    {
        return 'shopify_list_customers';
    }

    public function name(): string
    {
        return 'Shopify: List Customers';
    }

    public function description(): string
    {
        return 'Lists customers, newest first. As a trigger it starts the flow once for each new customer, instantly.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->credentialFields(),
                ...$this->windowFields(),
            ],
        ];
    }

    protected function path(): string
    {
        return '/customers.json';
    }

    protected function itemsKey(): string
    {
        return 'customers';
    }

    public function webhookTopic(): ?string
    {
        return 'customers/create';
    }

    public function triggerName(): string
    {
        return 'Shopify: New customer';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow once for each new customer, with their name, email and addresses.';
    }
}
