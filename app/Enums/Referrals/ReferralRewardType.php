<?php

namespace App\Enums\Referrals;

use App\Services\Referrals\Rewards\CreditsRewardHandler;
use App\Services\Referrals\Rewards\PlanTimeRewardHandler;
use App\Services\Referrals\Rewards\RewardHandler;
use App\Services\Referrals\Rewards\StripeBalanceRewardHandler;
use App\Services\Referrals\Rewards\TrialExtensionRewardHandler;

/**
 * What a reward gives. Each type is one `RewardHandler` class: adding a
 * type means a new case here and a new handler, nothing else.
 */
enum ReferralRewardType: string
{
    case Credits = 'credits';
    case PlanTime = 'plan_time';
    case StripeBalanceCredit = 'stripe_balance_credit';
    case TrialExtension = 'trial_extension';

    /**
     * @return class-string<RewardHandler>
     */
    public function handler(): string
    {
        return match ($this) {
            self::Credits => CreditsRewardHandler::class,
            self::PlanTime => PlanTimeRewardHandler::class,
            self::StripeBalanceCredit => StripeBalanceRewardHandler::class,
            self::TrialExtension => TrialExtensionRewardHandler::class,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Credits => 'Bonus credits',
            self::PlanTime => 'Free plan time',
            self::StripeBalanceCredit => 'Credit on the next invoice',
            self::TrialExtension => 'Longer free trial',
        };
    }
}
