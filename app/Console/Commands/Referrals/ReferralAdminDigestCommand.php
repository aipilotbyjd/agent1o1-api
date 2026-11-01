<?php

namespace App\Console\Commands\Referrals;

use App\Enums\Notifications\AlertSeverity;
use App\Enums\Referrals\ReferralRewardStatus;
use App\Enums\Referrals\ReferralStatus;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralReward;
use App\Services\Notifications\AdminAlerts;
use Illuminate\Console\Command;

/**
 * A daily note to the operators when the referral program needs a look:
 * rewards waiting for approval, or a burst of rejected (likely fraudulent)
 * signups. Says nothing on a quiet day.
 */
class ReferralAdminDigestCommand extends Command
{
    protected $signature = 'referrals:admin-digest';

    protected $description = 'Alerts platform admins about referral rewards awaiting approval and rejected signups.';

    public function handle(AdminAlerts $alerts): int
    {
        $awaiting = ReferralReward::query()->where('status', ReferralRewardStatus::AwaitingApproval)->count();
        $rejected = Referral::query()->where('status', ReferralStatus::Rejected)->where('rejected_at', '>=', now()->subDay())->count();
        $granted = ReferralReward::query()->where('status', ReferralRewardStatus::Granted)->where('granted_at', '>=', now()->subDay())->count();

        if ($awaiting === 0 && $rejected === 0) {
            $this->info('Nothing needs attention.');

            return self::SUCCESS;
        }

        $alerts->raise(
            key: 'referrals.daily_digest',
            title: 'Referral program: items need attention',
            body: "{$awaiting} reward(s) awaiting approval, {$rejected} signup(s) rejected and {$granted} reward(s) granted in the last 24 hours.",
            context: [
                'rewards_awaiting_approval' => $awaiting,
                'referrals_rejected_24h' => $rejected,
                'rewards_granted_24h' => $granted,
            ],
            severity: AlertSeverity::Info,
        );

        $this->info('Digest sent.');

        return self::SUCCESS;
    }
}
