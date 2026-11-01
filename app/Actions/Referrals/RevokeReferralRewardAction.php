<?php

namespace App\Actions\Referrals;

use App\Enums\Referrals\ReferralRewardStatus;
use App\Models\Referrals\ReferralReward;
use Illuminate\Support\Facades\DB;

/**
 * Withdraws a reward: a granted one is undone through its handler (credits
 * clawed back to zero at most, plan time shortened, invoice credit
 * debited); one still pending or awaiting approval is simply never granted.
 */
class RevokeReferralRewardAction
{
    public function execute(ReferralReward $reward, string $reason): bool
    {
        $revoked = DB::transaction(function () use ($reward, $reason): ?ReferralReward {
            $locked = ReferralReward::query()->whereKey($reward->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->status === ReferralRewardStatus::Revoked) {
                return null;
            }

            if ($locked->status === ReferralRewardStatus::Granted) {
                app($locked->reward_type->handler())->revoke($locked);
            }

            $locked->forceFill([
                'status' => ReferralRewardStatus::Revoked,
                'revoked_at' => now(),
                'revoked_reason' => $reason,
            ])->save();

            return $locked;
        });

        if ($revoked === null) {
            return false;
        }

        $reward->setRawAttributes($revoked->getAttributes(), true);

        return true;
    }
}
