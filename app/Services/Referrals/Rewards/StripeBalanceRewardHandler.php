<?php

namespace App\Services\Referrals\Rewards;

use App\Models\Referrals\ReferralReward;
use App\Services\Billing\StripeCustomerBalance;

/**
 * Money off the workspace's next Stripe invoice. How a paying subscriber
 * gets a "free month" that a plan-time grant couldn't give them: no cash
 * ever leaves the business.
 */
class StripeBalanceRewardHandler implements RewardHandler
{
    public function __construct(private readonly StripeCustomerBalance $balance) {}

    public function grant(ReferralReward $reward): void
    {
        $workspace = $reward->workspace;

        if ($workspace === null || ($reward->amount_cents ?? 0) <= 0) {
            return;
        }

        $reward->stripe_balance_transaction_id = $this->balance->credit($workspace, $reward->amount_cents, 'Referral reward', "referral-reward:{$reward->id}:grant");
    }

    public function revoke(ReferralReward $reward): void
    {
        $workspace = $reward->workspace;

        if ($workspace === null || ($reward->amount_cents ?? 0) <= 0 || $reward->stripe_balance_transaction_id === null) {
            return;
        }

        $this->balance->debit($workspace, $reward->amount_cents, 'Referral reward reversed', "referral-reward:{$reward->id}:revoke");
    }
}
