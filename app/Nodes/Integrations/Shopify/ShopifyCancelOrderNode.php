<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ShopifyCancelOrderNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_cancel_order';
    }

    public function name(): string
    {
        return 'Shopify: Cancel Order';
    }

    public function description(): string
    {
        return 'Cancels an order, optionally putting the items back in stock and telling the customer. This can\'t be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['order_id'],
            'properties' => [
                ...$this->credentialFields(),
                'order_id' => Field::text('Order ID', 'The number in the order\'s admin link, not the #1001 name.', '450789469'),
                'reason' => Field::select('Reason', ['customer' => 'Customer changed their mind', 'inventory' => 'Out of stock', 'fraud' => 'Fraud', 'declined' => 'Payment declined', 'other' => 'Other'], null, 'other'),
                'restock' => Field::boolean('Put the items back in stock'),
                'notify_customer' => Field::boolean('Email the customer'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $reason = (string) ($config['reason'] ?? 'other');

        if (! in_array($reason, ['customer', 'inventory', 'fraud', 'declined', 'other'], true)) {
            throw new InvalidArgumentException('Pick one of the reasons Shopify knows.');
        }

        $id = $this->shopifyId($config, 'order_id', 'order id');

        return (array) ($this->shopify($run, $config, 'POST', "/orders/{$id}/cancel.json", [
            'reason' => $reason,
            'restock' => filter_var($config['restock'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'email' => filter_var($config['notify_customer'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ]) ?: []);
    }
}
