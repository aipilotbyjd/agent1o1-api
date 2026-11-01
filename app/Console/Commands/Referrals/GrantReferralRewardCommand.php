<?php

namespace App\Console\Commands\Referrals;

use App\Enums\Referrals\ReferralRewardType;
use App\Models\Billing\Plan;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Referrals\ReferralAdmin;
use Illuminate\Console\Command;

/**
 * The command-line twin of `POST /admin/referrals/rewards/manual`: give a
 * user credits, plan time, an invoice credit or trial days directly.
 */
class GrantReferralRewardCommand extends Command
{
    protected $signature = 'referrals:grant
        {email : The recipient\'s email address}
        {--credits= : Bonus credits}
        {--plan= : Plan slug for free plan time (with --days)}
        {--days= : Days of free plan time (with --plan)}
        {--invoice-cents= : Credit on the next invoice, in cents}
        {--trial-days= : Extra free-trial days}
        {--workspace= : Workspace id to credit (defaults to one the user owns)}
        {--reason= : A note stored on the reward}';

    protected $description = 'Grants a goodwill referral reward to a user.';

    public function handle(ReferralAdmin $admin): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No user has that email address.');

            return self::FAILURE;
        }

        $values = $this->values();

        if ($values === null) {
            return self::FAILURE;
        }

        $workspace = $this->option('workspace') !== null ? Workspace::query()->find($this->option('workspace')) : null;

        if ($this->option('workspace') !== null && $workspace === null) {
            $this->error('No workspace has that id.');

            return self::FAILURE;
        }

        $reward = $admin->grantManualReward($user, $workspace, $values, $this->option('reason'), null);

        $this->info("Granted {$reward->reward_type->label()} to {$user->email} (reward {$reward->id}).");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function values(): ?array
    {
        if ($this->option('credits') !== null) {
            return ['reward_type' => ReferralRewardType::Credits, 'credits' => (int) $this->option('credits')];
        }

        if ($this->option('plan') !== null || $this->option('days') !== null) {
            $plan = Plan::query()->where('slug', $this->option('plan'))->first();

            if ($plan === null || (int) $this->option('days') <= 0) {
                $this->error('Free plan time needs --plan=<existing plan slug> and --days=<number>.');

                return null;
            }

            return ['reward_type' => ReferralRewardType::PlanTime, 'plan_id' => $plan->id, 'duration_days' => (int) $this->option('days')];
        }

        if ($this->option('invoice-cents') !== null) {
            return ['reward_type' => ReferralRewardType::StripeBalanceCredit, 'amount_cents' => (int) $this->option('invoice-cents')];
        }

        if ($this->option('trial-days') !== null) {
            return ['reward_type' => ReferralRewardType::TrialExtension, 'trial_days' => (int) $this->option('trial-days')];
        }

        $this->error('Pass one of --credits, --plan with --days, --invoice-cents or --trial-days.');

        return null;
    }
}
