<?php

namespace App\Nodes\Integrations\Shopify;

class ShopifyOrderCancelledNode extends ShopifyListOrdersNode
{
    public function type(): string
    {
        return 'shopify_order_cancelled';
    }

    public function name(): string
    {
        return 'Shopify: Order Cancelled';
    }

    public function description(): string
    {
        return 'Lists orders that were cancelled, most recently changed first. As a trigger it starts the flow once for each cancelled order, instantly.';
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
        return 'orders/cancelled';
    }

    protected function stamp(): string
    {
        return 'updated_at';
    }

    protected function fixedFilters(): array
    {
        return ['status' => 'cancelled'];
    }

    public function triggerName(): string
    {
        return 'Shopify: Order cancelled';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow once when an order is cancelled, with the reason and the order.';
    }

    /**
     * Paid and cancelled happen to an order once: a later edit of it is not another event.
     */
    protected function dedupeByChange(): bool
    {
        return false;
    }
}
