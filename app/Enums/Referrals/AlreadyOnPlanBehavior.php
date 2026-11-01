<?php

namespace App\Enums\Referrals;

/**
 * What a plan-time reward does when the recipient's workspace is already
 * entitled to that plan (or a more generous one) by something other than
 * referral time: free Pro days are worthless to a Pro subscriber.
 */
enum AlreadyOnPlanBehavior: string
{
    case GrantAnyway = 'grant_anyway';
    case Skip = 'skip';
    case ConvertToCredits = 'convert_to_credits';
    case StripeBalanceCredit = 'stripe_balance_credit';
}
