<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeGetSubscriptionNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_get_subscription';
    }

    public function name(): string
    {
        return 'Stripe: Get Subscription';
    }

    public function description(): string
    {
        return 'Reads one subscription.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['subscription_id'],
            'properties' => [
                ...$this->credentialFields(),
                'subscription_id' => Field::dynamic('Subscription', 'stripe.subscriptions', null, 'sub_...'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->stripe($run, $config, 'GET', '/subscriptions/'.$this->stripeId($config, 'subscription_id', 'sub', 'subscription id'));
    }
}
