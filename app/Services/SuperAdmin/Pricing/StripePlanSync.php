<?php

namespace App\Services\SuperAdmin\Pricing;

use App\Enums\Billing\BillingInterval;
use App\Models\Billing\Plan;
use App\Models\SuperAdmin\SuperAdmin;

/**
 * Brings a plan's Stripe product and prices in line with its amounts.
 *
 * Only ever *creates* what is missing (a product, or a price for an interval
 * that has an amount but no Stripe price). Anything already linked that
 * disagrees with Stripe (deleted, archived, different amount) is reported and
 * left alone: guessing at the fix could change what customers are charged.
 * A dry run does the same inspection and writes nothing.
 */
class StripePlanSync
{
    public function __construct(
        private readonly PricingStripe $stripe,
        private readonly PlanPriceChanger $prices,
    ) {}

    /**
     * @return array{applied: bool, product: string, prices: array<string, string>}
     */
    public function sync(SuperAdmin $actor, Plan $plan, bool $dryRun, string $reason): array
    {
        $report = [
            'applied' => ! $dryRun,
            'product' => $plan->stripe_product_id === null ? 'missing' : 'ok',
            'prices' => [],
        ];

        $toCreate = [];

        foreach (BillingInterval::cases() as $interval) {
            $amount = $plan->priceFor($interval);
            $priceId = $plan->stripePriceId($interval);

            if ($priceId === null) {
                $report['prices'][$interval->value] = $amount > 0 ? 'missing' : 'unpriced';

                if ($amount > 0) {
                    $toCreate[$interval->value] = $amount;
                }

                continue;
            }

            $report['prices'][$interval->value] = $this->statusOf($priceId, $amount);
        }

        if ($dryRun) {
            return $report;
        }

        if ($plan->stripe_product_id !== null) {
            $this->stripe->renameProduct($plan->stripe_product_id, $plan->name);
        }

        if ($toCreate !== []) {
            $this->prices->change($actor, $plan, $toCreate, $reason);

            $report['product'] = 'ok';

            foreach (array_keys($toCreate) as $value) {
                $report['prices'][$value] = 'created';
            }
        }

        return $report;
    }

    private function statusOf(string $priceId, int $expectedAmount): string
    {
        $price = $this->stripe->inspectPrice($priceId);

        return match (true) {
            ! $price['exists'] => 'missing_in_stripe',
            ! $price['active'] => 'archived_in_stripe',
            $price['amount_cents'] !== $expectedAmount => 'amount_mismatch',
            default => 'ok',
        };
    }
}
