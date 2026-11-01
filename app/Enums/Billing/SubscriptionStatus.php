<?php

namespace App\Enums\Billing;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Trialing = 'trialing';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Incomplete = 'incomplete';
    case IncompleteExpired = 'incomplete_expired';
    case Unpaid = 'unpaid';

    public function isUsable(): bool
    {
        return in_array($this, [self::Active, self::Trialing, self::PastDue], true);
    }
}
