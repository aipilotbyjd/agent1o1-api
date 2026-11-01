<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ShopifyCreateRefundNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_create_refund';
    }

    public function name(): string
    {
        return 'Shopify: Create Refund';
    }

    public function description(): string
    {
        return 'Refunds items, shipping or an amount on an order, and can put the items back in stock and tell the customer.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['order_id'],
            'properties' => [
                ...$this->credentialFields(),
                'order_id' => Field::text('Order ID', 'The number in the order\'s admin link, not the #1001 name.', '450789469'),
                'refund_line_items' => Field::json('Items to refund', 'A list of {"line_item_id": 123, "quantity": 1, "restock_type": "return"}. restock_type is no_restock, cancel or return.', 'array'),
                'transactions' => Field::json('Money to send back', 'A list of {"parent_id": 123, "amount": "9.99", "kind": "refund", "gateway": "bogus"}, as in the order\'s transactions.', 'array'),
                'full_shipping_refund' => Field::boolean('Refund the shipping too'),
                'note' => Field::text('Note'),
                'notify_customer' => Field::boolean('Email the customer'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->shopifyId($config, 'order_id', 'order id');
        $items = array_values(array_filter($this->decoded($config['refund_line_items'] ?? null, 'The items') ?? [], 'is_array'));
        $transactions = array_values(array_filter($this->decoded($config['transactions'] ?? null, 'The transactions') ?? [], 'is_array'));
        $shipping = filter_var($config['full_shipping_refund'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($items === [] && $transactions === [] && ! $shipping) {
            throw new InvalidArgumentException('Name the items, the money or the shipping to refund.');
        }

        foreach ($items as $item) {
            $this->shopifyId($item, 'line_item_id', 'line item id');
        }

        foreach ($transactions as $index => $transaction) {
            $transactions[$index] = [...$transaction, 'amount' => $this->price($transaction, 'amount', 'The amount'), 'kind' => 'refund'];
        }

        $refund = $this->shopify($run, $config, 'POST', "/orders/{$id}/refunds.json", ['refund' => [
            'refund_line_items' => $items !== [] ? $items : null,
            'transactions' => $transactions !== [] ? $transactions : null,
            'shipping' => $shipping ? ['full_refund' => true] : null,
            ...$this->filled($config, ['note']),
            'notify' => filter_var($config['notify_customer'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ]]);

        return (array) ($refund['refund'] ?? []);
    }
}
