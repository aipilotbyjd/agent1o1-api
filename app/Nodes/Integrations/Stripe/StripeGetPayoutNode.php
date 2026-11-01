<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeGetPayoutNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_get_payout';
    }

    public function name(): string
    {
        return 'Stripe: Get Payout';
    }

    public function description(): string
    {
        return 'Reads one payout, with its status and arrival date.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['payout_id'],
            'properties' => [
                ...$this->credentialFields(),
                'payout_id' => Field::text('Payout ID', null, 'po_...'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->stripe($run, $config, 'GET', '/payouts/'.$this->stripeId($config, 'payout_id', 'po', 'payout id'));
    }
}
