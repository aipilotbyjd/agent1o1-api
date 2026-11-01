<?php

namespace App\Nodes\Integrations\Shopify;

class ShopifyCustomerUpdatedNode extends ShopifyListCustomersNode
{
    public function type(): string
    {
        return 'shopify_customer_updated';
    }

    public function name(): string
    {
        return 'Shopify: Customer Updated';
    }

    public function description(): string
    {
        return 'Lists customers that changed lately. As a trigger it starts the flow each time a customer changes, instantly.';
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

    public function webhookTopic(): ?string
    {
        return 'customers/update';
    }

    protected function stamp(): string
    {
        return 'updated_at';
    }

    public function triggerName(): string
    {
        return 'Shopify: Customer updated';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow each time a customer changes (details, tags, consent), with the customer as they are now.';
    }
}
