<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Trialing = 'trialing';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Expired = 'expired';

    public function isUsable(): bool
    {
        return in_array($this, [self::Active, self::Trialing, self::Canceled], true);
    }
}
