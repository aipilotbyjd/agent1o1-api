<?php

namespace App\Nodes\Integrations\Shopify;

class ShopifyOrderUpdatedNode extends ShopifyListOrdersNode
{
    public function type(): string
    {
        return 'shopify_order_updated';
    }

    public function name(): string
    {
        return 'Shopify: Order Updated';
    }

    public function description(): string
    {
        return 'Lists orders that changed lately, most recently changed first. As a trigger it starts the flow each time an order changes, instantly.';
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
        return 'orders/updated';
    }

    protected function stamp(): string
    {
        return 'updated_at';
    }

    protected function fixedFilters(): array
    {
        return ['status' => 'any'];
    }

    public function triggerName(): string
    {
        return 'Shopify: Order updated';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow each time an order changes (paid, fulfilled, edited, tagged), with the order as it is now.';
    }
}
