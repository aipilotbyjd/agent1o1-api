<?php

namespace App\Services\Referrals;

use App\Enums\Referrals\ReferralRewardStatus;
use App\Enums\Referrals\ReferralTrigger;
use App\Models\Referrals\ReferralProgram;
use App\Models\Referrals\ReferralRewardRule;

/**
 * Dry-runs a program's rules against a hypothetical event so an admin can
 * see what a change would do before a real referral hits it. Reads only:
 * nothing is written. Workspace-dependent adjustments (already on the plan,
 * the monthly credit cap) are left out, since there is no real recipient.
 */
class ReferralSimulator
{
    public function __construct(
        private readonly ReferralRewardCalculator $calculator,
        private readonly ReferralRuleDescriber $describer,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function simulate(ReferralProgram $program, ReferralTrigger $trigger, TriggerContext $context, float $multiplier = 1.0): array
    {
        $rules = $program->rules()->where('trigger', $trigger)->with('plan')->get();

        return $rules->map(function (ReferralRewardRule $rule) use ($program, $trigger, $context, $multiplier): array {
            $reason = $this->reasonNotApplied($rule, $trigger, $context);
            $holdDays = $program->holdDaysFor($trigger, $rule->hold_days);
            $preview = $this->calculator->preview($rule, $multiplier);

            return [
                'rule_id' => $rule->id,
                'name' => $rule->name,
                'description' => $this->describer->describe($rule),
                'recipient' => $rule->recipient->value,
                'applies' => $reason === null,
                'reason' => $reason,
                'reward' => [
                    ...$preview,
                    'reward_type' => $preview['reward_type']->value,
                ],
                'hold_days' => $holdDays,
                'initial_status' => $program->requiresManualApproval()
                    ? ReferralRewardStatus::AwaitingApproval->value
                    : ($holdDays > 0 ? ReferralRewardStatus::Pending->value : ReferralRewardStatus::Granted->value),
            ];
        })->values()->all();
    }

    private function reasonNotApplied(ReferralRewardRule $rule, ReferralTrigger $trigger, TriggerContext $context): ?string
    {
        if (! $rule->isLive()) {
            return 'The rule is switched off or outside its date window.';
        }

        if ($trigger === ReferralTrigger::Milestone) {
            return ($context->convertedReferrals ?? 0) >= (int) $rule->milestone_count
                ? null
                : "Needs {$rule->milestone_count} converted referrals.";
        }

        return $rule->matches($context) ? null : "The event doesn't meet the rule's conditions.";
    }
}
