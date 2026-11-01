<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class StripeDeleteCouponNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_delete_coupon';
    }

    public function name(): string
    {
        return 'Stripe: Delete Coupon';
    }

    public function description(): string
    {
        return 'Deletes a coupon so it can\'t be used again. Customers who already have it keep it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['coupon_id'],
            'properties' => [
                ...$this->credentialFields(),
                'coupon_id' => Field::dynamic('Coupon', 'stripe.coupons', null, 'SUMMER20'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $coupon = $this->requiredString($config, 'coupon_id', 'The coupon');

        if (preg_match('/^[A-Za-z0-9_-]{1,100}$/', $coupon) !== 1) {
            throw new InvalidArgumentException("\"{$coupon}\" is not a Stripe coupon id.");
        }

        $result = $this->stripe($run, $config, 'DELETE', "/coupons/{$coupon}");

        return ['deleted' => (bool) ($result['deleted'] ?? false), 'coupon_id' => $coupon];
    }
}
