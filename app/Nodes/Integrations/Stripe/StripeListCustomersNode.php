<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeListCustomersNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_list_customers';
    }

    public function name(): string
    {
        return 'Stripe: List Customers';
    }

    public function description(): string
    {
        return 'Lists customers, newest first, optionally only the ones with an email address.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->credentialFields(),
                'email' => Field::text('Email', 'Only customers with exactly this email address.', 'ada@example.com'),
                'since' => Field::advanced(Field::datetime('Created after', 'Only records created at or after this time.')),
                'until' => Field::advanced(Field::datetime('Created before', 'Only records created at or before this time.')),
                'limit' => Field::integer('Max results', null, 25, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOutput($run, $config, '/customers', 'customers', [
            'email' => filled($config['email'] ?? null) ? trim((string) $config['email']) : null,
            'created' => $this->createdRange($config),
        ]);
    }
}
