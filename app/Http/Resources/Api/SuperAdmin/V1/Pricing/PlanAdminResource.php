<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Pricing;

use App\Enums\Billing\BillingInterval;
use App\Models\Billing\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A plan as an admin sees it, Stripe ids included: unlike the customer
 * `PlanResource`, which must never expose them.
 *
 * @mixin Plan
 */
class PlanAdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $prices = [];
        $stripePrices = [];

        foreach (BillingInterval::cases() as $interval) {
            $prices[$interval->value] = $this->priceFor($interval);
            $stripePrices[$interval->value] = $this->stripePriceId($interval);
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'prices' => $prices,
            'credits_monthly' => $this->credits_monthly,
            'limits' => $this->limits,
            'features' => array_keys(array_filter((array) $this->features)),
            'trial_days' => $this->trial_days,
            'badge' => $this->badge,
            'is_active' => $this->is_active,
            'is_public' => $this->is_public,
            'is_default' => $this->slug === config('billing.default_plan'),
            'archived_at' => $this->archived_at,
            'sort_order' => $this->sort_order,
            'enabled_intervals' => $this->enabled_intervals,
            'available_intervals' => array_map(fn (BillingInterval $interval): string => $interval->value, $this->availableIntervals()),
            'stripe' => ['product_id' => $this->stripe_product_id, 'prices' => $stripePrices],
            'subscribers_count' => $this->whenCounted('subscribers'),
            'grants_count' => $this->whenCounted('activeGrants'),
            'price_history' => $this->whenLoaded('priceHistory', fn () => $this->priceHistory->map(fn ($row): array => [
                'interval' => $row->interval->value,
                'stripe_price_id' => $row->stripe_price_id,
                'amount_cents' => $row->amount_cents,
                'retired_at' => $row->retired_at,
                'created_at' => $row->created_at,
            ])->all()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
