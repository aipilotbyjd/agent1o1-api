<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeCloseDisputeNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_close_dispute';
    }

    public function name(): string
    {
        return 'Stripe: Close Dispute';
    }

    public function description(): string
    {
        return 'Accepts a dispute and gives up the money. This can\'t be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['dispute_id'],
            'properties' => [
                ...$this->credentialFields(),
                'dispute_id' => Field::text('Dispute ID', null, 'dp_...'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->stripe($run, $config, 'POST', '/disputes/'.$this->stripeId($config, 'dispute_id', 'dp', 'dispute id').'/close');
    }
}
