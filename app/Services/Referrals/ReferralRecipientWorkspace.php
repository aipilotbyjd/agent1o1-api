<?php

namespace App\Services\Referrals;

use App\Enums\Referrals\ReferralRecipient;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralCode;
use App\Models\User;
use App\Models\Workspaces\Workspace;

/**
 * Which workspace a reward lands in. Rewards are workspace-level (credits,
 * plan grants and invoice credits all belong to a billable workspace), but
 * people refer people, so this picks the workspace for each side.
 */
class ReferralRecipientWorkspace
{
    public function for(Referral $referral, ReferralRecipient $recipient): ?Workspace
    {
        if ($recipient === ReferralRecipient::Referee) {
            return $referral->referredWorkspace ?? $referral->referredUser?->currentWorkspace;
        }

        return $referral->referrer !== null ? $this->forReferrer($referral->referrer, $referral->code) : null;
    }

    /**
     * The code's chosen reward workspace while the referrer still owns it,
     * else their current workspace if they own it, else the oldest one they
     * own. Rewards never land in a workspace someone else pays for.
     */
    public function forReferrer(User $referrer, ?ReferralCode $code = null): ?Workspace
    {
        $chosen = $code?->rewardWorkspace;

        if ($chosen !== null && $chosen->owner_id === $referrer->id) {
            return $chosen;
        }

        $current = $referrer->currentWorkspace;

        if ($current !== null && $current->owner_id === $referrer->id) {
            return $current;
        }

        return $referrer->ownedWorkspaces()->oldest()->first();
    }
}
