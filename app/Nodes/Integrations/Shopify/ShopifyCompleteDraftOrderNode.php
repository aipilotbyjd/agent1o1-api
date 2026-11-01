<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ShopifyCompleteDraftOrderNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_complete_draft_order';
    }

    public function name(): string
    {
        return 'Shopify: Complete Draft Order';
    }

    public function description(): string
    {
        return 'Turns a draft order into an order, paid or waiting for payment.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['draft_order_id'],
            'properties' => [
                ...$this->credentialFields(),
                'draft_order_id' => Field::text('Draft order ID', null, '994118539'),
                'payment_pending' => Field::boolean('Leave payment pending', 'On: the order waits for payment. Off: it is marked paid.', true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->shopifyId($config, 'draft_order_id', 'draft order id');

        return (array) ($this->shopify($run, $config, 'PUT', "/draft_orders/{$id}/complete.json", [], ['payment_pending' => filter_var($config['payment_pending'] ?? true, FILTER_VALIDATE_BOOLEAN)])['draft_order'] ?? []);
    }
}
