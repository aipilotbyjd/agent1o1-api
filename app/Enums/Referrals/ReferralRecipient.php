<?php

namespace App\Enums\Referrals;

enum ReferralRecipient: string
{
    case Referrer = 'referrer';
    case Referee = 'referee';
}
