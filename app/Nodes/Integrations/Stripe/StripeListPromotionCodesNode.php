<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeListPromotionCodesNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_list_promotion_codes';
    }

    public function name(): string
    {
        return 'Stripe: List Promotion Codes';
    }

    public function description(): string
    {
        return 'Lists the codes customers can type in to get a coupon.';
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
                'code' => Field::text('Code', 'Only this exact code.', 'SUMMER20'),
                'coupon_id' => Field::dynamic('Coupon', 'stripe.coupons', 'Only codes for this coupon.'),
                'active' => Field::select('Status', ['' => 'Any', 'true' => 'Active', 'false' => 'Inactive']),
                'limit' => Field::integer('Max results', null, 25, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOutput($run, $config, '/promotion_codes', 'promotion_codes', [
            'code' => filled($config['code'] ?? null) ? trim((string) $config['code']) : null,
            'coupon' => filled($config['coupon_id'] ?? null) ? trim((string) $config['coupon_id']) : null,
            'active' => filled($config['active'] ?? null) ? filter_var($config['active'], FILTER_VALIDATE_BOOLEAN) : null,
        ]);
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
