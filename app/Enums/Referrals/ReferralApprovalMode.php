<?php

namespace App\Enums\Referrals;

enum ReferralApprovalMode: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';
}
