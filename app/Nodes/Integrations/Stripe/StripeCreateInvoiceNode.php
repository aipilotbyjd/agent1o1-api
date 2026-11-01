<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class StripeCreateInvoiceNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_create_invoice';
    }

    public function name(): string
    {
        return 'Stripe: Create Invoice';
    }

    public function description(): string
    {
        return 'Creates a draft invoice for a customer from their pending invoice items.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['customer_id'],
            'properties' => [
                ...$this->credentialFields(),
                'customer_id' => Field::dynamic('Customer', 'stripe.customers', null, 'cus_...'),
                'collection_method' => Field::select('Collection', ['charge_automatically' => 'Charge automatically', 'send_invoice' => 'Email the customer to pay'], null, 'charge_automatically'),
                'days_until_due' => Field::integer('Days until due', 'For an emailed invoice.', null, 1, 365),
                'description' => Field::advanced(Field::text('Description')),
                'metadata' => Field::advanced(Field::keyValue('Metadata', 'Your own notes on the record, such as an order id.')),
                'idempotency_key' => $this->idempotencyField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $method = (string) ($config['collection_method'] ?? 'charge_automatically');

        if ($method === 'send_invoice' && blank($config['days_until_due'] ?? null)) {
            throw new InvalidArgumentException('An invoice emailed to the customer needs the days until it is due.');
        }

        return $this->stripe($run, $config, 'POST', '/invoices', [
            'customer' => $this->stripeId($config, 'customer_id', 'cus', 'customer id'),
            'collection_method' => $method,
            'days_until_due' => $method === 'send_invoice' ? $this->amount($config, 'days_until_due', 'The days until due') : null,
            ...$this->filled($config, ['description']),
            'metadata' => $this->metadata($config),
        ], $this->idempotencyKey($config));
    }
}
