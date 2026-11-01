<?php

namespace App\Enums\Referrals;

/**
 * A referral only moves forward (`Pending` → `Verified` → `Activated` →
 * `Converted`) except into `Rejected`, which a fraud check or an admin
 * can put it in from anywhere.
 */
enum ReferralStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Activated = 'activated';
    case Converted = 'converted';
    case Rejected = 'rejected';

    public function rank(): int
    {
        return match ($this) {
            self::Rejected => -1,
            self::Pending => 0,
            self::Verified => 1,
            self::Activated => 2,
            self::Converted => 3,
        };
    }
}
