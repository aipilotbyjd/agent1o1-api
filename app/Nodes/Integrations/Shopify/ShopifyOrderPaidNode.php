<?php

namespace App\Nodes\Integrations\Shopify;

class ShopifyOrderPaidNode extends ShopifyListOrdersNode
{
    public function type(): string
    {
        return 'shopify_order_paid';
    }

    public function name(): string
    {
        return 'Shopify: Order Paid';
    }

    public function description(): string
    {
        return 'Lists orders that have been paid, most recently changed first. As a trigger it starts the flow once for each order when it is paid, instantly.';
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
        return 'orders/paid';
    }

    protected function stamp(): string
    {
        return 'updated_at';
    }

    protected function fixedFilters(): array
    {
        return ['status' => 'any', 'financial_status' => 'paid'];
    }

    public function triggerName(): string
    {
        return 'Shopify: Order paid';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow once when an order is paid, with its items, customer and totals.';
    }

    /**
     * Paid and cancelled happen to an order once: a later edit of it is not another event.
     */
    protected function dedupeByChange(): bool
    {
        return false;
    }
}
