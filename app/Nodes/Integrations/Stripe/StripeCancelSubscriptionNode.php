<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeCancelSubscriptionNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_cancel_subscription';
    }

    public function name(): string
    {
        return 'Stripe: Cancel Subscription';
    }

    public function description(): string
    {
        return 'Cancels a subscription now, or when the period the customer paid for ends.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['subscription_id'],
            'properties' => [
                ...$this->credentialFields(),
                'subscription_id' => Field::dynamic('Subscription', 'stripe.subscriptions', null, 'sub_...'),
                'when' => Field::select('When', ['period_end' => 'At the end of the period', 'now' => 'Right now'], null, 'period_end'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $subscription = $this->stripeId($config, 'subscription_id', 'sub', 'subscription id');

        if ((string) ($config['when'] ?? 'period_end') === 'now') {
            return $this->stripe($run, $config, 'DELETE', "/subscriptions/{$subscription}");
        }

        return $this->stripe($run, $config, 'POST', "/subscriptions/{$subscription}", ['cancel_at_period_end' => true]);
    }
}
