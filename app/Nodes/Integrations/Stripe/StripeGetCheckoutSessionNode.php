<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeGetCheckoutSessionNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_get_checkout_session';
    }

    public function name(): string
    {
        return 'Stripe: Get Checkout Session';
    }

    public function description(): string
    {
        return 'Reads a Checkout session, with its payment status.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['session_id'],
            'properties' => [
                ...$this->credentialFields(),
                'session_id' => Field::text('Session ID', null, 'cs_...'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->stripe($run, $config, 'GET', '/checkout/sessions/'.$this->stripeId($config, 'session_id', 'cs', 'checkout session id'));
    }
}
