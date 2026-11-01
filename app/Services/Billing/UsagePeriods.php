<?php

namespace App\Services\Billing;

use App\Enums\Billing\CreditGrantSource;
use App\Models\Billing\CreditGrant;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\Billing\UsagePeriod;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\Policies\CreditAllowancePolicy;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Opens and closes usage periods, and gives each one its allowance.
 *
 * A subscribed workspace's periods follow its Stripe billing cycle, sliced
 * into months (a yearly plan still refills monthly); anything else uses the
 * calendar month. So a customer who subscribes on the 28th gets one
 * allowance until the 28th of next month, not a second one on the 1st.
 *
 * The allowance itself comes from `CreditAllowancePolicy` and is stored as
 * the period's `credits_limit`, which `CreditWallet::ensureAllowance()`
 * turns into the period's grant.
 */
class UsagePeriods
{
    public function __construct(
        private readonly CreditAllowancePolicy $allowances,
        private readonly CreditWallet $wallet,
    ) {}

    /**
     * The period covering now, opening one if none does.
     */
    public function current(Workspace $workspace): UsagePeriod
    {
        $now = now();

        $existing = $workspace->usagePeriods()
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            ->orderByDesc('starts_at')
            ->first();

        return $existing ?? $this->open($workspace);
    }

    /**
     * Brings the current period in line with the subscription Stripe just
     * reported: a new billing cycle closes the old period and opens a new
     * one; a plan change within the cycle resizes the allowance in place,
     * keeping what was already used.
     */
    public function syncSubscription(Workspace $workspace, ?Plan $plan, ?Subscription $subscription): UsagePeriod
    {
        return DB::transaction(function () use ($workspace, $plan, $subscription): UsagePeriod {
            $now = now();
            $decision = $this->allowances->decide($workspace, $plan, $subscription);

            $current = $workspace->usagePeriods()
                ->where('starts_at', '<=', $now)
                ->where('ends_at', '>', $now)
                ->orderByDesc('starts_at')
                ->lockForUpdate()
                ->first();

            [$windowStart] = $this->windowFor($workspace, $decision['subscription'], $now);

            if ($current !== null && $this->followsBillingCycle($decision['subscription']) && $windowStart->gt($current->starts_at)) {
                $this->close($current, $windowStart);
                $current = null;
            }

            if ($current === null) {
                return $this->open($workspace, $decision);
            }

            $current->update([
                'plan_id' => $decision['plan']?->id,
                'subscription_id' => $decision['subscription']?->id,
                'credits_limit' => $decision['credits'],
            ]);

            $this->wallet->ensureAllowance($current, $decision['source']);

            return $current;
        });
    }

    /**
     * Re-decides the current period's allowance, e.g. once the owner verifies
     * their email.
     */
    public function refreshAllowance(Workspace $workspace): UsagePeriod
    {
        $period = $this->current($workspace);
        $decision = $this->allowances->decide($workspace);

        if ($period->credits_limit !== $decision['credits']) {
            $period->update(['credits_limit' => $decision['credits']]);
        }

        $this->wallet->ensureAllowance($period, $decision['source']);

        return $period;
    }

    /**
     * @param  array{source: CreditGrantSource, credits: int|null, plan: Plan|null, subscription: Subscription|null}|null  $decision
     */
    public function open(Workspace $workspace, ?array $decision = null): UsagePeriod
    {
        $now = now();
        $decision ??= $this->allowances->decide($workspace);

        [$startsAt, $endsAt] = $this->windowFor($workspace, $decision['subscription'], $now);

        $previous = $workspace->usagePeriods()->orderByDesc('ends_at')->first();

        if ($previous !== null && $previous->ends_at->gt($startsAt)) {
            $startsAt = CarbonImmutable::parse($previous->ends_at);
        }

        if ($startsAt->gte($endsAt)) {
            $endsAt = $startsAt->addMonthNoOverflow();
        }

        $period = $workspace->usagePeriods()->firstOrCreate(
            ['starts_at' => $startsAt],
            [
                'ends_at' => $endsAt,
                'plan_id' => $decision['plan']?->id,
                'subscription_id' => $decision['subscription']?->id,
                'credits_limit' => $decision['credits'],
            ],
        );

        if ($period->wasRecentlyCreated) {
            $this->wallet->ensureAllowance($period, $decision['source']);
            $this->rollOver($workspace, $previous, $period, $decision);
        }

        return $period;
    }

    /**
     * The window a period opened now would cover.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function windowFor(Workspace $workspace, ?Subscription $subscription, CarbonInterface $at): array
    {
        $at = CarbonImmutable::parse($at);

        if ($this->followsBillingCycle($subscription)
            && $at->gte($subscription->current_period_starts_at)
            && $at->lt($subscription->current_period_ends_at)) {
            $cycleStart = CarbonImmutable::parse($subscription->current_period_starts_at);
            $cycleEnd = CarbonImmutable::parse($subscription->current_period_ends_at);

            $months = (int) $cycleStart->diffInMonths($at);
            $start = $cycleStart->addMonthsNoOverflow($months);

            if ($start->gt($at)) {
                $start = $cycleStart->addMonthsNoOverflow(max(0, $months - 1));
            }

            $end = $start->addMonthNoOverflow();

            return [$start, $end->gt($cycleEnd) ? $cycleEnd : $end];
        }

        $start = $at->startOfMonth();

        return [$start, $start->addMonthNoOverflow()];
    }

    private function followsBillingCycle(?Subscription $subscription): bool
    {
        return $subscription !== null
            && $subscription->current_period_starts_at !== null
            && $subscription->current_period_ends_at !== null;
    }

    /**
     * Ends a period early (a new billing cycle started) and retires what is
     * left of its allowance.
     */
    private function close(UsagePeriod $period, CarbonInterface $endsAt): void
    {
        $period->update(['ends_at' => $endsAt]);

        CreditGrant::query()
            ->where('usage_period_id', $period->id)
            ->whereIn('source', CreditGrantSource::allowance())
            ->whereNull('revoked_at')
            ->get()
            ->each(fn (CreditGrant $grant): int => $this->wallet->revoke($grant, 'Billing cycle ended'));
    }

    /**
     * Carries part of the previous period's unused plan allowance into the
     * new period, when the plan allows rollover. The rolled-over credits
     * expire with the new period, so they never pile up.
     *
     * @param  array{source: CreditGrantSource, credits: int|null, plan: Plan|null, subscription: Subscription|null}  $decision
     */
    private function rollOver(Workspace $workspace, ?UsagePeriod $previous, UsagePeriod $period, array $decision): void
    {
        $percent = $decision['plan']?->creditRolloverPercent() ?? 0;

        if ($previous === null || $percent === 0 || $decision['source'] !== CreditGrantSource::Plan) {
            return;
        }

        $unused = (int) CreditGrant::query()
            ->where('usage_period_id', $previous->id)
            ->where('source', CreditGrantSource::Plan)
            ->whereNull('revoked_at')
            ->sum('remaining');

        $credits = intdiv($unused * $percent, 100);

        if ($credits <= 0) {
            return;
        }

        $this->wallet->grant($workspace, CreditGrantSource::Rollover, $credits, [
            'idempotency_key' => 'rollover:'.$previous->id,
            'expires_at' => $period->ends_at,
            'reason' => "{$percent}% of last period's unused credits",
        ]);
    }
}
