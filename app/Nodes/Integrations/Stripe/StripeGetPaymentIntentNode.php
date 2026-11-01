<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeGetPaymentIntentNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_get_payment_intent';
    }

    public function name(): string
    {
        return 'Stripe: Get Payment Intent';
    }

    public function description(): string
    {
        return 'Reads one payment, with its status.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['payment_intent_id'],
            'properties' => [
                ...$this->credentialFields(),
                'payment_intent_id' => Field::text('Payment intent ID', null, 'pi_...'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->stripe($run, $config, 'GET', '/payment_intents/'.$this->stripeId($config, 'payment_intent_id', 'pi', 'payment intent id'));
    }
}
