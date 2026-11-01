<?php

namespace App\Console\Commands\Billing;

use App\Actions\Billing\OpenUsagePeriodForSubscriptionAction;
use App\Enums\Billing\PlanGrantStatus;
use App\Models\Billing\PlanGrant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Processes fixed-term grants (earned referral plan time, comps) whose
 * `expires_at` has passed. The grant already stopped entitling the moment
 * it lapsed (`PlanGrant::scopeActive()` checks the date) but the usage
 * period's `credits_limit` was sized while it was live and is otherwise
 * only re-sized at the next month boundary, so without this the lapsed
 * plan's allowance would stay spendable until month end.
 *
 * Flips each grant to `Expired` first, so it is processed exactly once.
 */
class ExpirePlanGrantsCommand extends Command
{
    protected $signature = 'billing:expire-plan-grants';

    protected $description = 'Marks lapsed fixed-term plan grants expired and re-sizes their workspaces\' usage periods.';

    public function handle(OpenUsagePeriodForSubscriptionAction $openUsagePeriod): int
    {
        $expired = 0;

        PlanGrant::query()
            ->where('status', PlanGrantStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->with('workspace')
            ->chunkById(100, function ($grants) use ($openUsagePeriod, &$expired): void {
                foreach ($grants as $grant) {
                    DB::transaction(function () use ($grant, $openUsagePeriod): void {
                        $grant->update(['status' => PlanGrantStatus::Expired]);

                        $workspace = $grant->workspace;

                        if ($workspace === null) {
                            return;
                        }

                        $openUsagePeriod->execute(
                            $workspace,
                            $workspace->currentPlan(),
                            $workspace->activeSubscription()?->stripe_id,
                        );
                    });

                    $expired++;
                }
            });

        $this->info("Expired {$expired} plan grant(s).");

        return self::SUCCESS;
    }
}
