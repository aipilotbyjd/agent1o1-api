<?php

namespace App\Services\Referrals;

use App\Enums\Billing\PlanGrantSource;
use App\Enums\Referrals\AlreadyOnPlanBehavior;
use App\Enums\Referrals\ReferralRecipient;
use App\Enums\Referrals\ReferralRewardType;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Referrals\ReferralProgram;
use App\Models\Referrals\ReferralReward;
use App\Models\Referrals\ReferralRewardRule;
use App\Models\User;
use App\Models\Workspaces\Workspace;

/**
 * Turns a rule into what one reward actually gives: the rule's values,
 * scaled by the referral code's multiplier (referrer rewards only), swapped
 * for another reward type when free plan time would be worthless, and
 * trimmed to the referrer's monthly credit cap.
 */
class ReferralRewardCalculator
{
    /**
     * The rule's raw values with the multiplier applied: no workspace
     * state involved. Used by `simulate` and as the starting point of
     * `calculate()`.
     *
     * @return array{reward_type: ReferralRewardType, credits: ?int, plan_id: ?string, duration_days: ?int, amount_cents: ?int, trial_days: ?int, notes: ?string}
     */
    public function preview(ReferralRewardRule $rule, float $multiplier = 1.0): array
    {
        $scale = $rule->recipient === ReferralRecipient::Referrer ? max($multiplier, 0) : 1.0;

        $amountCents = $rule->amount_cents;

        if ($amountCents === null && $rule->amount_percent_of_plan !== null && $rule->plan !== null) {
            $amountCents = (int) round($rule->plan->price_monthly * $rule->amount_percent_of_plan / 100);
        }

        return [
            'reward_type' => $rule->reward_type,
            'credits' => $this->scaled($rule->credits_amount, $scale),
            'plan_id' => $rule->plan_id,
            'duration_days' => $this->scaled($rule->duration_days, $scale),
            'amount_cents' => $this->scaled($amountCents, $scale),
            'trial_days' => $rule->trial_days,
            'notes' => null,
        ];
    }

    /**
     * What `$rule` gives `$recipient` in `$workspace` right now, or `null`
     * when it gives nothing (skipped as already on the plan, or the credit
     * cap is spent).
     *
     * @return array{reward_type: ReferralRewardType, credits: ?int, plan_id: ?string, duration_days: ?int, amount_cents: ?int, trial_days: ?int, notes: ?string}|null
     */
    public function calculate(ReferralProgram $program, ReferralRewardRule $rule, User $recipient, ?Workspace $workspace, float $multiplier = 1.0): ?array
    {
        if ($workspace === null) {
            return null;
        }

        $values = $this->preview($rule, $multiplier);

        if ($values['reward_type'] === ReferralRewardType::PlanTime) {
            $values = $this->applyAlreadyOnPlan($rule, $workspace, $values);

            if ($values === null) {
                return null;
            }
        }

        if ($values['reward_type'] === ReferralRewardType::Credits && $rule->recipient === ReferralRecipient::Referrer) {
            $values = $this->applyMonthlyCreditCap($program, $recipient, $values);
        }

        return $this->givesSomething($values) ? $values : null;
    }

    /**
     * The best plan the workspace holds *other than* through referral time:
     * a subscription or a purchased/comped grant. Referral grants are
     * left out so earned time can keep stacking.
     */
    public function paidEntitlement(Workspace $workspace): ?Plan
    {
        $plans = $workspace->planGrants()
            ->active()
            ->where('source', '!=', PlanGrantSource::Referral)
            ->with('plan')
            ->get()
            ->map(fn (PlanGrant $grant): ?Plan => $grant->plan)
            ->push($workspace->activeSubscription()?->plan)
            ->filter();

        return $plans->sortByDesc(fn (Plan $plan): int => $plan->creditsMonthly())->first();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>|null
     */
    private function applyAlreadyOnPlan(ReferralRewardRule $rule, Workspace $workspace, array $values): ?array
    {
        $plan = $rule->plan;
        $held = $this->paidEntitlement($workspace);

        if ($plan === null || $held === null || $held->creditsMonthly() < $plan->creditsMonthly()) {
            return $values;
        }

        $days = (int) $values['duration_days'];
        $behavior = $rule->if_already_on_plan;

        if ($behavior === AlreadyOnPlanBehavior::StripeBalanceCredit && $workspace->stripe_id === null) {
            $behavior = AlreadyOnPlanBehavior::ConvertToCredits;
        }

        return match ($behavior) {
            AlreadyOnPlanBehavior::GrantAnyway => $values,
            AlreadyOnPlanBehavior::Skip => null,
            AlreadyOnPlanBehavior::ConvertToCredits => [
                ...$values,
                'reward_type' => ReferralRewardType::Credits,
                'credits' => $rule->fallback_credits ?? (int) round($plan->creditsMonthly() * $days / 30),
                'duration_days' => null,
                'notes' => "Already on {$held->name}: given as credits instead of {$days} days of {$plan->name}.",
            ],
            AlreadyOnPlanBehavior::StripeBalanceCredit => [
                ...$values,
                'reward_type' => ReferralRewardType::StripeBalanceCredit,
                'amount_cents' => (int) round($plan->price_monthly * $days / 30),
                'duration_days' => null,
                'notes' => "Already on {$held->name}: given as an invoice credit instead of {$days} days of {$plan->name}.",
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function applyMonthlyCreditCap(ReferralProgram $program, User $recipient, array $values): array
    {
        $cap = $program->referrer_monthly_credit_cap;

        if ($cap === null) {
            return $values;
        }

        $used = (int) ReferralReward::query()
            ->notRevoked()
            ->where('recipient_user_id', $recipient->id)
            ->where('recipient_role', ReferralRecipient::Referrer)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('credits');

        $allowed = max(0, $cap - $used);

        if ((int) $values['credits'] > $allowed) {
            $values['notes'] = trim(($values['notes'] ?? '')." Trimmed from {$values['credits']} to the monthly cap.");
            $values['credits'] = $allowed;
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function givesSomething(array $values): bool
    {
        return match ($values['reward_type']) {
            ReferralRewardType::Credits => ($values['credits'] ?? 0) > 0,
            ReferralRewardType::PlanTime => $values['plan_id'] !== null && ($values['duration_days'] ?? 0) > 0,
            ReferralRewardType::StripeBalanceCredit => ($values['amount_cents'] ?? 0) > 0,
            ReferralRewardType::TrialExtension => ($values['trial_days'] ?? 0) > 0,
        };
    }

    private function scaled(?int $value, float $scale): ?int
    {
        return $value === null ? null : (int) round($value * $scale);
    }
}
