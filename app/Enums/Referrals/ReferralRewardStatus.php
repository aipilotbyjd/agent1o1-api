<?php

namespace App\Enums\Referrals;

/**
 * `AwaitingApproval` only occurs in a program set to manual approval;
 * `Pending` is a reward inside its hold period, granted by
 * `referrals:grant-pending` once `grant_after` passes.
 */
enum ReferralRewardStatus: string
{
    case AwaitingApproval = 'awaiting_approval';
    case Pending = 'pending';
    case Granted = 'granted';
    case Revoked = 'revoked';

    public function isOpen(): bool
    {
        return $this === self::AwaitingApproval || $this === self::Pending;
    }
}
