<?php

namespace App\Console\Commands\Referrals;

use App\Enums\Billing\PlanGrantSource;
use App\Enums\Billing\PlanGrantStatus;
use App\Models\Billing\PlanGrant;
use App\Notifications\Referrals\ReferralPlanTimeEndingNotification;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Console\Command;

/**
 * Reminds a workspace a few days (`referrals.plan_time_ending_notice_days`)
 * before its earned referral plan time runs out: the moment to subscribe.
 * Each grant is reminded once; extending it clears the stamp.
 */
class NotifyReferralPlanTimeEndingCommand extends Command
{
    protected $signature = 'referrals:notify-plan-time-ending';

    protected $description = 'Warns workspaces whose free referral plan time is about to end.';

    public function handle(NotificationDispatcher $notifications): int
    {
        $notified = 0;

        PlanGrant::query()
            ->where('source', PlanGrantSource::Referral)
            ->where('status', PlanGrantStatus::Active)
            ->whereNull('expiry_notified_at')
            ->whereBetween('expires_at', [now(), now()->addDays((int) config('referrals.plan_time_ending_notice_days', 3))])
            ->with(['workspace', 'plan'])
            ->chunkById(100, function ($grants) use ($notifications, &$notified): void {
                foreach ($grants as $grant) {
                    if ($grant->workspace !== null) {
                        $notifications->dispatch(
                            $notifications->ownersAndAdmins($grant->workspace),
                            new ReferralPlanTimeEndingNotification($grant->workspace, $grant),
                        );
                    }

                    $grant->update(['expiry_notified_at' => now()]);
                    $notified++;
                }
            });

        $this->info("Sent {$notified} plan-time-ending reminder(s).");

        return self::SUCCESS;
    }
}
