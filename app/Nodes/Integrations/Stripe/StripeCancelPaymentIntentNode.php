<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeCancelPaymentIntentNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_cancel_payment_intent';
    }

    public function name(): string
    {
        return 'Stripe: Cancel Payment Intent';
    }

    public function description(): string
    {
        return 'Cancels a payment that has not been paid yet.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['payment_intent_id'],
            'properties' => [
                ...$this->credentialFields(),
                'payment_intent_id' => Field::text('Payment intent ID', null, 'pi_...'),
                'reason' => Field::select('Reason', ['duplicate' => 'Duplicate', 'fraudulent' => 'Fraudulent', 'requested_by_customer' => 'Requested by customer', 'abandoned' => 'Abandoned']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $reason = (string) ($config['reason'] ?? '');

        return $this->stripe($run, $config, 'POST', '/payment_intents/'.$this->stripeId($config, 'payment_intent_id', 'pi', 'payment intent id').'/cancel', [
            'cancellation_reason' => $reason !== '' ? $reason : null,
        ]);
    }
}
