<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeCreateProductNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_create_product';
    }

    public function name(): string
    {
        return 'Stripe: Create Product';
    }

    public function description(): string
    {
        return 'Creates a product to put prices on.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name', null, 'Pro plan'),
                'description' => Field::text('Description'),
                'metadata' => Field::advanced(Field::keyValue('Metadata', 'Your own notes on the record, such as an order id.')),
                'idempotency_key' => $this->idempotencyField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->stripe($run, $config, 'POST', '/products', [
            'name' => $this->requiredString($config, 'name', 'The name'),
            ...$this->filled($config, ['description']),
            'metadata' => $this->metadata($config),
        ], $this->idempotencyKey($config));
    }
}
