<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class StripeCreatePromotionCodeNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_create_promotion_code';
    }

    public function name(): string
    {
        return 'Stripe: Create Promotion Code';
    }

    public function description(): string
    {
        return 'Makes a code customers can type in to get a coupon.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['coupon_id'],
            'properties' => [
                ...$this->credentialFields(),
                'coupon_id' => Field::dynamic('Coupon', 'stripe.coupons', null, 'SUMMER20'),
                'code' => Field::text('Code', 'What the customer types. Leave empty and Stripe makes one.', 'SUMMER20'),
                'max_redemptions' => Field::integer('Most uses', null, null, 1),
                'expires_at' => Field::datetime('Expires'),
                'first_time_transaction' => Field::advanced(Field::boolean('First purchase only', 'Only for customers who have never paid.')),
                'metadata' => Field::advanced(Field::keyValue('Metadata', 'Your own notes on the record, such as an order id.')),
                'idempotency_key' => $this->idempotencyField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $code = trim((string) ($config['code'] ?? ''));

        if ($code !== '' && preg_match('/^[A-Za-z0-9]{3,500}$/', $code) !== 1) {
            throw new InvalidArgumentException('A code is letters and numbers only, at least three.');
        }

        $first = filter_var($config['first_time_transaction'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $this->stripe($run, $config, 'POST', '/promotion_codes', [
            'coupon' => $this->requiredString($config, 'coupon_id', 'The coupon'),
            'code' => $code !== '' ? $code : null,
            'max_redemptions' => filled($config['max_redemptions'] ?? null) ? $this->amount($config, 'max_redemptions', 'The most uses') : null,
            'expires_at' => filled($config['expires_at'] ?? null) ? Carbon::parse((string) $config['expires_at'])->getTimestamp() : null,
            'restrictions' => $first ? ['first_time_transaction' => true] : null,
            'metadata' => $this->metadata($config),
        ], $this->idempotencyKey($config));
    }

    /**
     * Promotion codes name their coupon `coupon` in this version; an account on a
     * newer one would need a different request, so the call says which it means.
     */
    protected function apiVersion(): ?string
    {
        return '2024-06-20';
    }
}
