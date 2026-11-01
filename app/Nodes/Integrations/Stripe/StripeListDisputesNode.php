<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeListDisputesNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_list_disputes';
    }

    public function name(): string
    {
        return 'Stripe: List Disputes';
    }

    public function description(): string
    {
        return 'Lists disputes (chargebacks), newest first.';
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
                'charge_id' => Field::advanced(Field::text('Charge ID', null, 'ch_...')),
                'payment_intent_id' => Field::advanced(Field::text('Payment intent ID', null, 'pi_...')),
                'since' => Field::advanced(Field::datetime('Created after', 'Only records created at or after this time.')),
                'until' => Field::advanced(Field::datetime('Created before', 'Only records created at or before this time.')),
                'limit' => Field::integer('Max results', null, 25, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOutput($run, $config, '/disputes', 'disputes', [
            'charge' => $this->optionalId($config, 'charge_id', 'ch', 'charge id'),
            'payment_intent' => $this->optionalId($config, 'payment_intent_id', 'pi', 'payment intent id'),
            'created' => $this->createdRange($config),
        ]);
    }
}
