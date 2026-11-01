<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeListSubscriptionsNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_list_subscriptions';
    }

    public function name(): string
    {
        return 'Stripe: List Subscriptions';
    }

    public function description(): string
    {
        return 'Lists subscriptions, newest first. Cancelled ones are left out unless you ask for them.';
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
                'customer_id' => Field::dynamic('Customer', 'stripe.customers', null, 'cus_...'),
                'price_id' => Field::advanced(Field::dynamic('Price', 'stripe.prices', null, 'price_...')),
                'status' => Field::select('Status', ['' => 'Not cancelled', 'all' => 'All', 'active' => 'Active', 'trialing' => 'Trialing', 'past_due' => 'Past due', 'unpaid' => 'Unpaid', 'canceled' => 'Cancelled', 'incomplete' => 'Incomplete', 'paused' => 'Paused']),
                'since' => Field::advanced(Field::datetime('Created after', 'Only records created at or after this time.')),
                'until' => Field::advanced(Field::datetime('Created before', 'Only records created at or before this time.')),
                'limit' => Field::integer('Max results', null, 25, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOutput($run, $config, '/subscriptions', 'subscriptions', [
            'customer' => $this->optionalId($config, 'customer_id', 'cus', 'customer id'),
            'price' => $this->optionalId($config, 'price_id', 'price', 'price id'),
            'status' => filled($config['status'] ?? null) ? (string) $config['status'] : null,
            'created' => $this->createdRange($config),
        ]);
    }
}
