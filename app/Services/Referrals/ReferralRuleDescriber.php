<?php

namespace App\Services\Referrals;

use App\Enums\Referrals\ReferralRecipient;
use App\Enums\Referrals\ReferralRewardType;
use App\Enums\Referrals\ReferralTrigger;
use App\Models\Billing\Plan;
use App\Models\Referrals\ReferralReward;
use App\Models\Referrals\ReferralRewardRule;

/**
 * Plain-English terms for rules and rewards, written from the referrer's
 * point of view ("You get…", "Your friend gets…"). The frontend shows a
 * program's terms from this, so its copy changes the moment a rule does.
 */
class ReferralRuleDescriber
{
    public function describe(ReferralRewardRule $rule): string
    {
        $who = $rule->recipient === ReferralRecipient::Referrer ? 'You get' : 'Your friend gets';
        $what = $this->valueSummary($rule->reward_type, $rule->credits_amount, $rule->plan, $rule->duration_days, $rule->amount_cents, $rule->trial_days, $rule->amount_percent_of_plan);

        return "{$who} {$what} {$this->when($rule)}.";
    }

    public function rewardSummary(ReferralReward $reward): string
    {
        return $this->valueSummary($reward->reward_type, $reward->credits, $reward->plan, $reward->duration_days, $reward->amount_cents, $reward->trial_days);
    }

    private function valueSummary(ReferralRewardType $type, ?int $credits, ?Plan $plan, ?int $days, ?int $amountCents, ?int $trialDays, ?int $percentOfPlan = null): string
    {
        return match ($type) {
            ReferralRewardType::Credits => number_format((int) $credits).' bonus credits',
            ReferralRewardType::PlanTime => $this->days((int) $days).' of '.($plan?->name ?? 'a paid plan').' free',
            ReferralRewardType::StripeBalanceCredit => $amountCents !== null
                ? $this->dollars($amountCents).' off the next invoice'
                : "{$percentOfPlan}% of a month of ".($plan?->name ?? 'the plan').' off the next invoice',
            ReferralRewardType::TrialExtension => $this->days((int) $trialDays).' of extra free trial',
        };
    }

    private function when(ReferralRewardRule $rule): string
    {
        $conditions = $rule->conditions ?? [];

        // "Your friend gets 500 credits when they sign up…" rather than
        // repeating "your friend" in both halves.
        $aboutReferee = $rule->recipient === ReferralRecipient::Referee;

        $phrase = match ($rule->trigger) {
            ReferralTrigger::SignupVerified => $aboutReferee ? 'when they sign up and verify their email' : 'when your friend signs up and verifies their email',
            ReferralTrigger::Activated => $aboutReferee ? 'when they run their first workflow or agent' : 'when your friend runs their first workflow or agent',
            ReferralTrigger::FirstPayment => $aboutReferee ? 'when they make their first payment' : 'when your friend makes their first payment',
            ReferralTrigger::RepeatPayment => $aboutReferee ? 'each time they pay again' : 'each time your friend pays again',
            ReferralTrigger::Milestone => 'when '.(int) $rule->milestone_count.' of your referrals have become paying customers',
            ReferralTrigger::Manual => 'as a thank-you',
        };

        if (isset($conditions['min_payment_cents'])) {
            $phrase .= ' (payments of '.$this->dollars((int) $conditions['min_payment_cents']).' or more)';
        }

        if (! empty($conditions['billing_intervals'])) {
            $phrase .= ' on a '.implode(' or ', (array) $conditions['billing_intervals']).' plan';
        }

        if ($rule->max_per_recipient !== null && $rule->trigger === ReferralTrigger::RepeatPayment) {
            $phrase .= ", up to {$rule->max_per_recipient} times";
        }

        return $phrase;
    }

    private function dollars(int $cents): string
    {
        return '$'.number_format($cents / 100, $cents % 100 === 0 ? 0 : 2);
    }

    private function days(int $days): string
    {
        return $days === 1 ? '1 day' : "{$days} days";
    }
}
