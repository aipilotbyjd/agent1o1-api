<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class StripeGetBalanceNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_get_balance';
    }

    public function name(): string
    {
        return 'Stripe: Get Balance';
    }

    public function description(): string
    {
        return 'Reads the account balance: what is available to pay out and what is still pending, per currency.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $balance = $this->stripe($run, $config, 'GET', '/balance');

        return ['available' => $balance['available'] ?? [], 'pending' => $balance['pending'] ?? [], 'livemode' => $balance['livemode'] ?? null];
    }
}
