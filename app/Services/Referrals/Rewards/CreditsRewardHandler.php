<?php

namespace App\Services\Referrals\Rewards;

use App\Enums\Billing\CreditGrantSource;
use App\Models\Billing\CreditGrant;
use App\Models\Referrals\ReferralReward;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\CreditWallet;

/**
 * Pays a referral reward as a `referral` grant in the workspace's
 * `CreditWallet`: it carries over between periods and is spent after the
 * period's allowance. The grant references the reward, so a clawback takes
 * back exactly what is left of it.
 */
class CreditsRewardHandler implements RewardHandler
{
    public function __construct(private readonly CreditWallet $wallet) {}

    public function grant(ReferralReward $reward): void
    {
        if ($reward->workspace_id === null || ($reward->credits ?? 0) <= 0) {
            return;
        }

        $workspace = Workspace::query()->find($reward->workspace_id);

        if ($workspace === null) {
            return;
        }

        $this->wallet->grant($workspace, CreditGrantSource::Referral, (int) $reward->credits, [
            'idempotency_key' => 'referral:'.$reward->id,
            'reference' => $reward,
            'reason' => 'Referral reward',
        ]);
    }

    /**
     * Takes back what is still unspent of the reward's grant. Credits already
     * spent stay spent, and `credits_clawed_back` records how much was
     * actually recovered.
     */
    public function revoke(ReferralReward $reward): void
    {
        if ($reward->workspace_id === null || ($reward->credits ?? 0) <= 0) {
            return;
        }

        $grant = CreditGrant::query()
            ->where('reference_type', $reward->getMorphClass())
            ->where('reference_id', $reward->getKey())
            ->first();

        $reward->credits_clawed_back = $grant !== null
            ? $this->wallet->revoke($grant, 'Referral reward revoked')
            : 0;
    }
}
