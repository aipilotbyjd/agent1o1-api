<?php

namespace Database\Factories\Referrals;

use App\Enums\Referrals\ReferralRecipient;
use App\Enums\Referrals\ReferralRewardType;
use App\Enums\Referrals\ReferralTrigger;
use App\Models\Billing\Plan;
use App\Models\Referrals\ReferralProgram;
use App\Models\Referrals\ReferralRewardRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferralRewardRule>
 */
class ReferralRewardRuleFactory extends Factory
{
    protected $model = ReferralRewardRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id' => ReferralProgram::factory(),
            'name' => fake()->sentence(3),
            'trigger' => ReferralTrigger::SignupVerified,
            'recipient' => ReferralRecipient::Referee,
            'reward_type' => ReferralRewardType::Credits,
            'credits_amount' => 500,
        ];
    }

    public function forProgram(ReferralProgram $program): static
    {
        return $this->state(fn (): array => ['program_id' => $program->id]);
    }

    public function on(ReferralTrigger $trigger, ReferralRecipient $recipient = ReferralRecipient::Referrer): static
    {
        return $this->state(fn (): array => ['trigger' => $trigger, 'recipient' => $recipient]);
    }

    public function credits(int $amount): static
    {
        return $this->state(fn (): array => [
            'reward_type' => ReferralRewardType::Credits,
            'credits_amount' => $amount,
        ]);
    }

    public function planTime(Plan $plan, int $days): static
    {
        return $this->state(fn (): array => [
            'reward_type' => ReferralRewardType::PlanTime,
            'credits_amount' => null,
            'plan_id' => $plan->id,
            'duration_days' => $days,
        ]);
    }

    public function stripeBalance(int $cents): static
    {
        return $this->state(fn (): array => [
            'reward_type' => ReferralRewardType::StripeBalanceCredit,
            'credits_amount' => null,
            'amount_cents' => $cents,
        ]);
    }

    public function trialExtension(int $days): static
    {
        return $this->state(fn (): array => [
            'reward_type' => ReferralRewardType::TrialExtension,
            'recipient' => ReferralRecipient::Referee,
            'credits_amount' => null,
            'trial_days' => $days,
        ]);
    }

    public function milestone(int $count): static
    {
        return $this->state(fn (): array => [
            'trigger' => ReferralTrigger::Milestone,
            'recipient' => ReferralRecipient::Referrer,
            'milestone_count' => $count,
        ]);
    }
}
