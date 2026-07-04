<?php

namespace App\Console\Commands\Billing;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\SubscriptionService;
use Illuminate\Console\Command;

class ExpireTrialsCommand extends Command
{
    protected $signature = 'billing:expire-trials';

    protected $description = 'Downgrade trialing subscriptions whose trial ended with no payment.';

    public function __construct(private SubscriptionService $subscriptionService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $freePlan = Plan::where('slug', 'free')->first();

        if (! $freePlan) {
            return self::SUCCESS;
        }

        $expired = Subscription::where('status', SubscriptionStatus::Trialing)
            ->where('trial_ends_at', '<', now())
            ->whereNull('stripe_subscription_id')
            ->get();

        foreach ($expired as $sub) {
            $sub->update([
                'plan_id' => $freePlan->id,
                'status' => SubscriptionStatus::Active,
                'credits_per_cycle' => $freePlan->creditsMonthly(),
            ]);

            $sub->workspace->invalidatePlanCache();
        }

        $this->info("Expired {$expired->count()} trial(s).");

        return self::SUCCESS;
    }
}
