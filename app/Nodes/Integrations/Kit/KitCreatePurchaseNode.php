<?php

namespace App\Nodes\Integrations\Kit;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class KitCreatePurchaseNode extends AbstractKitNode
{
    public function type(): string
    {
        return 'kit_create_purchase';
    }

    public function name(): string
    {
        return 'Kit: Record Purchase';
    }

    public function description(): string
    {
        return 'Records a purchase for a subscriber (adding them if new), for segments and automations on buyers.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email', 'transaction_id', 'product_name', 'unit_price'],
            'properties' => [
                ...$this->credentialFields(),
                'email' => Field::text('Buyer email', null, 'ada@example.com'),
                'transaction_id' => Field::text('Transaction id', 'Your order id. A repeat of the same id is not recorded twice.', 'order-1001'),
                'product_name' => Field::text('Product name', null, 'Course'),
                'unit_price' => Field::text('Price', 'The price of one, e.g. 49.00.', '49.00'),
                'quantity' => Field::integer('Quantity', null, 1, 1, 10000),
                'currency' => Field::text('Currency', 'Three-letter code.', 'USD'),
                'status' => Field::select('Status', ['paid' => 'Paid', 'pending' => 'Pending', 'refunded' => 'Refunded', 'failed' => 'Failed'], null, 'paid'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $price = $config['unit_price'] ?? null;

        if (! is_numeric($price) || (float) $price < 0) {
            throw new InvalidArgumentException('The price must be a number, e.g. 49.00.');
        }

        $purchase = (array) data_get((array) $this->kit($run, $config, 'POST', '/purchases', ['purchase' => [
            'email_address' => $this->email($config),
            'transaction_id' => $this->requiredString($config, 'transaction_id', 'The transaction id'),
            'status' => (string) ($config['status'] ?? 'paid'),
            'currency' => strtoupper((string) ($config['currency'] ?? 'USD')),
            'products' => [['name' => $this->requiredString($config, 'product_name', 'The product name'), 'quantity' => max(1, (int) ($config['quantity'] ?? 1)), 'unit_price' => (float) $price, 'lid' => (string) ($config['transaction_id'] ?? ''), 'pid' => (string) crc32($this->requiredString($config, 'product_name', 'The product name'))]],
        ]]), 'purchase', []);

        return ['id' => $purchase['id'] ?? null, 'transaction_id' => $purchase['transaction_id'] ?? null, 'status' => $purchase['status'] ?? null, 'total' => $purchase['total'] ?? null, 'email' => $purchase['email_address'] ?? null];
    }
}
