<?php

namespace App\Services\SuperAdmin\Pricing;

use App\Enums\Billing\BillingInterval;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanPriceHistory;
use App\Models\SuperAdmin\SuperAdmin;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Changes what a plan costs for *new* purchases.
 *
 * Stripe prices are immutable, so a new amount is a new Stripe price. The old
 * price is archived in Stripe (existing subscriptions on it keep billing at
 * the old amount: that is the grandfathering) and its id is kept in
 * `plan_price_history`, so `Plan::findByStripePriceId()` still maps those
 * subscribers back to their plan.
 *
 * Order matters: Stripe prices are created first and the local rows written
 * in one transaction after, so a Stripe failure leaves nothing half-applied;
 * if the local write fails the freshly made prices are archived again. Old
 * prices are only archived once the new ones are safely recorded.
 */
class PlanPriceChanger
{
    public function __construct(
        private readonly PricingStripe $stripe,
        private readonly PricingChangeLogger $log,
    ) {}

    /**
     * @param  array<string, int>  $amounts  Interval value => new amount in cents. Intervals already at that amount are skipped.
     */
    public function change(SuperAdmin $actor, Plan $plan, array $amounts, string $reason): Plan
    {
        $changes = $this->changesFor($plan, $amounts);

        if ($changes === []) {
            return $plan;
        }

        $productId = $this->ensureProduct($plan);
        $created = [];

        try {
            foreach ($changes as $value => $change) {
                $created[$value] = $this->stripe->createPrice(
                    $productId,
                    BillingInterval::from($value),
                    $change['new_amount'],
                    "plan-price:{$plan->id}:{$value}:{$change['new_amount']}:".($change['old_price_id'] ?? 'none'),
                );
            }

            $plan = DB::transaction(fn (): Plan => $this->record($actor, $plan, $changes, $created, $reason));
        } catch (Throwable $e) {
            foreach ($created as $priceId) {
                try {
                    $this->stripe->archivePrice($priceId);
                } catch (Throwable $cleanup) {
                    report($cleanup);
                }
            }

            throw $e instanceof ApiErrorException
                ? new HttpException(502, 'Stripe rejected the price change: '.$e->getMessage(), $e)
                : $e;
        }

        foreach ($changes as $change) {
            if ($change['old_price_id'] === null) {
                continue;
            }

            try {
                $this->stripe->archivePrice($change['old_price_id']);
            } catch (Throwable $e) {
                // Already recorded as retired locally; Stripe can be tidied later.
                report($e);
            }
        }

        return $plan;
    }

    /**
     * @param  array<string, int>  $amounts
     * @return array<string, array{old_amount: int, new_amount: int, old_price_id: string|null}>
     */
    private function changesFor(Plan $plan, array $amounts): array
    {
        $changes = [];

        foreach ($amounts as $value => $cents) {
            $interval = BillingInterval::from($value);
            $oldAmount = $plan->priceFor($interval);
            $oldPriceId = $plan->stripePriceId($interval);

            if ($cents === 0 && $oldPriceId !== null) {
                throw new HttpException(422, "Plan [{$plan->slug}] already sells on [{$value}]. Switch that interval off instead of pricing it at zero.");
            }

            $unchanged = $cents === $oldAmount && ($oldPriceId !== null || $cents === 0);

            if ($unchanged || $cents === 0) {
                continue;
            }

            $changes[$value] = ['old_amount' => $oldAmount, 'new_amount' => $cents, 'old_price_id' => $oldPriceId];
        }

        return $changes;
    }

    private function ensureProduct(Plan $plan): string
    {
        if ($plan->stripe_product_id !== null) {
            return $plan->stripe_product_id;
        }

        $productId = $this->stripe->createProduct($plan->name, $plan->slug, "plan-product:{$plan->id}");

        $plan->forceFill(['stripe_product_id' => $productId])->save();

        return $productId;
    }

    /**
     * @param  array<string, array{old_amount: int, new_amount: int, old_price_id: string|null}>  $changes
     * @param  array<string, string>  $created
     */
    private function record(SuperAdmin $actor, Plan $plan, array $changes, array $created, string $reason): Plan
    {
        $locked = Plan::query()->whereKey($plan->getKey())->lockForUpdate()->firstOrFail();

        foreach ($changes as $value => $change) {
            if ($locked->stripePriceId(BillingInterval::from($value)) !== $change['old_price_id']) {
                throw new HttpException(409, 'The plan was changed while this price was being updated. Reload it and try again.');
            }
        }

        $before = [];
        $after = [];

        foreach ($changes as $value => $change) {
            $interval = BillingInterval::from($value);
            $column = $interval->stripePriceColumn();

            if ($change['old_price_id'] !== null) {
                PlanPriceHistory::query()->updateOrCreate(
                    ['stripe_price_id' => $change['old_price_id']],
                    ['plan_id' => $locked->id, 'interval' => $value, 'amount_cents' => $change['old_amount'], 'retired_at' => now()],
                );
            }

            PlanPriceHistory::query()->create([
                'plan_id' => $locked->id,
                'interval' => $value,
                'stripe_price_id' => $created[$value],
                'amount_cents' => $change['new_amount'],
                'created_by' => $actor->id,
            ]);

            $before["price_{$value}"] = $change['old_amount'];
            $before[$column] = $change['old_price_id'];
            $after["price_{$value}"] = $change['new_amount'];
            $after[$column] = $created[$value];

            $locked->setAttribute("price_{$value}", $change['new_amount']);
            $locked->setAttribute($column, $created[$value]);
        }

        $locked->updated_by = $actor->id;
        $locked->save();

        $this->log->record($actor, 'plan', 'prices_changed', $locked, $before, $after, $reason);

        return $locked;
    }
}
