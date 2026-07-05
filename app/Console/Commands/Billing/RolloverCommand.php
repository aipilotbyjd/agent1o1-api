<?php

namespace App\Console\Commands\Billing;

use App\Enums\CreditTransactionType;
use App\Enums\SubscriptionStatus;
use App\Models\CreditTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsagePeriod;
use App\Services\Billing\CreditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RolloverCommand extends Command
{
    protected $signature = 'billing:rollover';

    protected $description = 'Roll over expired usage periods and open new ones.';

    public function __construct(private CreditService $creditService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $expired = UsagePeriod::where('is_current', true)
            ->whereDate('period_end', '<', today())
            ->with(['workspace.subscription.plan'])
            ->get();

        foreach ($expired as $period) {
            DB::transaction(function () use ($period) {
                $period->update(['is_current' => false]);

                $workspace = $period->workspace;
                $subscription = $period->subscription;

                $newLimit = $this->resolveNewLimit($subscription);
                $rollover = $this->computeRollover($period);

                $newPeriod = UsagePeriod::create([
                    'workspace_id' => $workspace->id,
                    'subscription_id' => $subscription?->id,
                    'period_start' => $period->period_end->addDay(),
                    'period_end' => $period->period_end->addDay()->addMonthNoOverflow(),
                    'credits_limit' => $newLimit,
                    'credits_rolled_over' => $rollover,
                    'is_current' => true,
                ]);

                if ($newLimit > 0) {
                    CreditTransaction::create([
                        'workspace_id' => $workspace->id,
                        'usage_period_id' => $newPeriod->id,
                        'type' => CreditTransactionType::Grant,
                        'credits' => $newLimit,
                        'description' => 'Period grant.',
                        'subject_type' => UsagePeriod::class,
                        'subject_id' => $newPeriod->id,
                        'created_at' => now(),
                    ]);
                }

                if ($rollover > 0) {
                    CreditTransaction::create([
                        'workspace_id' => $workspace->id,
                        'usage_period_id' => $newPeriod->id,
                        'type' => CreditTransactionType::Rollover,
                        'credits' => $rollover,
                        'description' => 'Pack credits rolled over.',
                        'subject_type' => UsagePeriod::class,
                        'subject_id' => $period->id,
                        'created_at' => now(),
                    ]);
                }

                if ($subscription
                    && $subscription->status === SubscriptionStatus::Canceled
                    && $subscription->current_period_end?->isPast()
                ) {
                    $subscription->update(['status' => SubscriptionStatus::Expired]);
                    $workspace->invalidatePlanCache();
                }

                $workspace->load('currentPeriod');
                $this->creditService->syncRedis($workspace);
            });
        }

        $this->info("Rolled over {$expired->count()} period(s).");

        return self::SUCCESS;
    }

    private function resolveNewLimit(?Subscription $subscription): int
    {
        if (! $subscription) {
            return $this->freePlanCredits();
        }

        $usable = in_array($subscription->status, [
            SubscriptionStatus::Active,
            SubscriptionStatus::Trialing,
            SubscriptionStatus::Canceled,
        ]);

        if (! $usable) {
            return $this->freePlanCredits();
        }

        if ($subscription->status === SubscriptionStatus::Canceled
            && $subscription->current_period_end?->isPast()
        ) {
            return $this->freePlanCredits();
        }

        return $subscription->credits_per_cycle;
    }

    private function computeRollover(UsagePeriod $period): int
    {
        // Option A: only pack credits roll over, once
        if ($period->credits_from_packs <= 0) {
            return 0;
        }

        $packUsed = max(0, $period->credits_used - $period->credits_limit);
        $packUnused = max(0, $period->credits_from_packs - $packUsed);

        return $packUnused;
    }

    private function freePlanCredits(): int
    {
        // Parenthesize the coalesce: the cast binds tighter than ??, so
        // `(int) $x ?? 1000` is `((int) $x) ?? 1000` — and `(int) null` is 0
        // (not null), which means the 1000 fallback could never fire.
        return (int) (Plan::where('slug', 'free')->value('limits->credits_monthly') ?? 1000);
    }
}
