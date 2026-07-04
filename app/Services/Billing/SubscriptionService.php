<?php

namespace App\Services\Billing;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsagePeriod;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    public function __construct(
        private CreditService $creditService,
        private StripeService $stripeService,
    ) {}

    public function bootstrapFree(Workspace $workspace): void
    {
        $freePlan = Plan::where('slug', 'free')->firstOrFail();

        DB::transaction(function () use ($workspace, $freePlan) {
            $subscription = Subscription::create([
                'workspace_id' => $workspace->id,
                'plan_id' => $freePlan->id,
                'status' => SubscriptionStatus::Active,
                'billing_interval' => BillingInterval::Monthly,
                'credits_per_cycle' => $freePlan->creditsMonthly(),
            ]);

            UsagePeriod::create([
                'workspace_id' => $workspace->id,
                'subscription_id' => $subscription->id,
                'period_start' => today(),
                'period_end' => today()->addMonthNoOverflow(),
                'credits_limit' => $freePlan->creditsMonthly(),
                'is_current' => true,
            ]);
        });

        $workspace->load('currentPeriod');
        $this->creditService->syncRedis($workspace);
    }

    public function checkout(Workspace $workspace, Plan $plan, string $interval): array
    {
        $trialDays = 0;

        $trialedSlugs = $workspace->trialed_plan_slugs ?? [];
        if ($plan->trial_days > 0 && ! in_array($plan->slug, $trialedSlugs, true)) {
            $trialDays = $plan->trial_days;
        }

        return [
            'url' => $this->buildCheckoutUrl($workspace, $plan, $interval, $trialDays),
            'trial_days' => $trialDays,
        ];
    }

    public function swap(Workspace $workspace, Plan $plan, string $interval): Subscription
    {
        $subscription = $workspace->subscription;

        DB::transaction(function () use ($workspace, $subscription, $plan, $interval) {
            $subscription->update([
                'plan_id' => $plan->id,
                'billing_interval' => $interval,
                'credits_per_cycle' => $plan->creditsMonthly(),
                'stripe_price_id' => $plan->stripe_prices[$interval] ?? null,
            ]);

            $workspace->invalidatePlanCache();
            $this->transitionPeriod($workspace, $subscription);
        });

        return $subscription->fresh();
    }

    public function cancel(Workspace $workspace): Subscription
    {
        $subscription = $workspace->subscription;
        $subscription->update(['canceled_at' => now()]);
        $workspace->invalidatePlanCache();

        return $subscription->fresh();
    }

    public function resume(Workspace $workspace): Subscription
    {
        $subscription = $workspace->subscription;
        $subscription->update(['canceled_at' => null, 'status' => SubscriptionStatus::Active]);
        $workspace->invalidatePlanCache();

        return $subscription->fresh();
    }

    public function transitionPeriod(Workspace $workspace, Subscription $subscription): void
    {
        $currentPeriod = $workspace->currentPeriod;

        if ($currentPeriod) {
            $currentPeriod->update(['is_current' => false]);
        }

        $packRemainder = $currentPeriod?->credits_from_packs
            ? max(0, $currentPeriod->credits_from_packs - max(0, $currentPeriod->credits_used - $currentPeriod->credits_limit))
            : 0;

        UsagePeriod::create([
            'workspace_id' => $workspace->id,
            'subscription_id' => $subscription->id,
            'period_start' => today(),
            'period_end' => $subscription->current_period_end ?? today()->addMonthNoOverflow(),
            'credits_limit' => $subscription->credits_per_cycle,
            'credits_rolled_over' => $packRemainder,
            'is_current' => true,
        ]);

        $workspace->load('currentPeriod');
        $this->creditService->syncRedis($workspace);
    }

    public function activateFromCheckout(array $stripeSession, array $stripeSub): void
    {
        $workspaceId = $stripeSession['client_reference_id'] ?? null;

        if (! $workspaceId) {
            return;
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace) {
            return;
        }

        $plan = Plan::where('stripe_product_id', $stripeSub['plan']['product'] ?? null)->first()
            ?? Plan::where('slug', 'free')->first();

        DB::transaction(function () use ($workspace, $stripeSub, $stripeSession, $plan) {
            $subscription = Subscription::updateOrCreate(
                ['workspace_id' => $workspace->id],
                [
                    'plan_id' => $plan->id,
                    'stripe_subscription_id' => $stripeSub['id'],
                    'stripe_customer_id' => $stripeSession['customer'],
                    'stripe_price_id' => $stripeSub['items']['data'][0]['price']['id'] ?? null,
                    'status' => $stripeSub['status'],
                    'billing_interval' => ($stripeSub['items']['data'][0]['plan']['interval'] ?? 'month') === 'year'
                        ? BillingInterval::Yearly
                        : BillingInterval::Monthly,
                    'credits_per_cycle' => $plan->creditsMonthly(),
                    'current_period_start' => Carbon::createFromTimestamp($stripeSub['current_period_start']),
                    'current_period_end' => Carbon::createFromTimestamp($stripeSub['current_period_end']),
                    'trial_ends_at' => $stripeSub['trial_end']
                        ? Carbon::createFromTimestamp($stripeSub['trial_end'])
                        : null,
                ]
            );

            if ($plan->trial_days > 0) {
                $slugs = $workspace->trialed_plan_slugs ?? [];
                $slugs[] = $plan->slug;
                $workspace->update(['trialed_plan_slugs' => array_unique($slugs)]);
            }

            // Store Stripe customer ID on the workspace for future checkouts
            if ($stripeSession['customer'] && ! $workspace->stripe_id) {
                $workspace->update(['stripe_id' => $stripeSession['customer']]);
            }

            $workspace->invalidatePlanCache();

            // Idempotency guard: Stripe retries webhooks on any non-2xx or timeout.
            // Only open a new billing period if one does not already exist that started
            // on this Stripe billing cycle's period_start date.
            $periodStart = Carbon::createFromTimestamp($stripeSub['current_period_start'])->toDateString();

            $alreadyActivated = UsagePeriod::where('workspace_id', $workspace->id)
                ->where('subscription_id', $subscription->id)
                ->where('period_start', $periodStart)
                ->exists();

            if ($alreadyActivated) {
                Log::info('activateFromCheckout skipped — period already exists.', [
                    'workspace_id' => $workspace->id,
                    'stripe_subscription_id' => $stripeSub['id'],
                    'period_start' => $periodStart,
                ]);

                return;
            }

            $this->transitionPeriod($workspace, $subscription);
        });
    }

    public function portalUrl(Workspace $workspace): string
    {
        $session = $this->stripeService->createBillingPortalSession([
            'customer' => $workspace->stripe_id,
            'return_url' => config('app.frontend_url').'/billing',
        ]);

        return $session->url;
    }

    private function buildCheckoutUrl(Workspace $workspace, Plan $plan, string $interval, int $trialDays): string
    {
        $params = [
            'mode' => 'subscription',
            'client_reference_id' => $workspace->id,
            'line_items' => [['price' => $plan->stripe_prices[$interval] ?? null, 'quantity' => 1]],
            'success_url' => config('app.frontend_url').'/billing?checkout=success',
            'cancel_url' => config('app.frontend_url').'/billing',
        ];

        if ($workspace->stripe_id) {
            $params['customer'] = $workspace->stripe_id;
        } else {
            $params['customer_email'] = $workspace->owner->email;
        }

        if ($trialDays > 0) {
            $params['subscription_data'] = ['trial_period_days' => $trialDays];
        }

        $session = $this->stripeService->createSubscriptionCheckoutSession($params);

        return $session->url;
    }
}
