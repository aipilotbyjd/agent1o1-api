<?php

namespace App\Enums\Referrals;

enum ReferralPaymentSource: string
{
    case Subscription = 'subscription';
    case CreditPack = 'credit_pack';
    case Lifetime = 'lifetime';
}
