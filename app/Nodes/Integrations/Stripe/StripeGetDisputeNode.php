<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeGetDisputeNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_get_dispute';
    }

    public function name(): string
    {
        return 'Stripe: Get Dispute';
    }

    public function description(): string
    {
        return 'Reads one dispute, with its reason, status and the date the evidence is due.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        return $this->stripe($run, $config, 'GET', '/disputes/'.$this->stripeId($config, 'dispute_id', 'dp', 'dispute id'));
    }
}
