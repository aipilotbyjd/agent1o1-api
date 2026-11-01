<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ShopifyListOrderTransactionsNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_list_order_transactions';
    }

    public function name(): string
    {
        return 'Shopify: List Order Transactions';
    }

    public function description(): string
    {
        return 'Lists the payments, refunds and authorizations of an order.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['order_id'],
            'properties' => [
                ...$this->credentialFields(),
                'order_id' => Field::text('Order ID', 'The number in the order\'s admin link, not the #1001 name.', '450789469'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $transactions = (array) ($this->shopify($run, $config, 'GET', "/orders/{$this->shopifyId($config, 'order_id', 'order id')}/transactions.json")['transactions'] ?? []);

        return ['transactions' => $transactions, 'count' => count($transactions)];
    }
}
