<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeCreateCustomerNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_create_customer';
    }

    public function name(): string
    {
        return 'Stripe: Create Customer';
    }

    public function description(): string
    {
        return 'Creates a customer.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->credentialFields(),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'name' => Field::text('Name', null, 'Ada Lovelace'),
                'phone' => Field::advanced(Field::text('Phone')),
                'description' => Field::advanced(Field::text('Description')),
                'metadata' => Field::advanced(Field::keyValue('Metadata', 'Your own notes on the record, such as an order id.')),
                'idempotency_key' => $this->idempotencyField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->stripe($run, $config, 'POST', '/customers', [
            ...$this->filled($config, ['email', 'name', 'phone', 'description']),
            'metadata' => $this->metadata($config),
        ], $this->idempotencyKey($config));
    }
}
