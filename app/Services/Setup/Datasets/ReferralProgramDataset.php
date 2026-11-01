<?php

namespace App\Services\Setup\Datasets;

use App\Enums\Referrals\AlreadyOnPlanBehavior;
use App\Enums\Referrals\ReferralRecipient;
use App\Enums\Referrals\ReferralRewardType;
use App\Enums\Referrals\ReferralTrigger;
use App\Enums\Setup\Ownership;
use App\Models\Billing\Plan;
use App\Models\Referrals\ReferralBlockedDomain;
use App\Models\Referrals\ReferralProgram;
use App\Services\Setup\AbstractSetupDataset;

/**
 * A starting point for the referral program, not its source of truth: it
 * only creates the default program (and its rules) when no program with
 * that slug exists yet, so re-running it never overwrites what an admin has
 * since changed from the admin API.
 */
class ReferralProgramDataset extends AbstractSetupDataset
{
    public function key(): string
    {
        return 'referral-program';
    }

    public function label(): string
    {
        return 'Referral program';
    }

    public function ownership(): Ownership
    {
        return Ownership::AdminOwned;
    }

    public function dependencies(): array
    {
        return ['plans'];
    }

    public function rows(): int
    {
        return ReferralProgram::query()->count();
    }

    /**
     * Common disposable-mail services. Maintained from the admin API after
     * this.
     *
     * @var list<string>
     */
    private const array BLOCKED_DOMAINS = [
        'mailinator.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com',
        'temp-mail.org', 'yopmail.com', 'trashmail.com', 'sharklasers.com',
        'getnada.com', 'dispostable.com', 'maildrop.cc', 'throwawaymail.com',
    ];

    public function apply(): void
    {
        foreach (self::BLOCKED_DOMAINS as $domain) {
            ReferralBlockedDomain::query()->firstOrCreate(['domain' => $domain], ['reason' => 'Disposable email service']);
        }

        if (ReferralProgram::withTrashed()->where('slug', 'default')->exists()) {
            return;
        }

        $program = ReferralProgram::query()->create([
            'name' => 'Default',
            'slug' => 'default',
            'description' => 'Invite friends: they get bonus credits, you get credits and free Pro time.',
            'is_active' => true,
            'is_default' => true,
            'default_hold_days' => 14,
            'referrer_monthly_credit_cap' => 10000,
            'referrer_max_stacked_plan_days' => 365,
        ]);

        $pro = Plan::query()->where('slug', 'pro')->first();

        $rules = [
            [
                'name' => 'Welcome credits for the new user',
                'trigger' => ReferralTrigger::SignupVerified,
                'recipient' => ReferralRecipient::Referee,
                'reward_type' => ReferralRewardType::Credits,
                'credits_amount' => 500,
            ],
            [
                'name' => 'Credits when your friend gets started',
                'trigger' => ReferralTrigger::Activated,
                'recipient' => ReferralRecipient::Referrer,
                'reward_type' => ReferralRewardType::Credits,
                'credits_amount' => 1000,
            ],
        ];

        if ($pro !== null) {
            $rules = [
                ...$rules,
                [
                    'name' => 'A month of Pro when your friend subscribes',
                    'trigger' => ReferralTrigger::FirstPayment,
                    'recipient' => ReferralRecipient::Referrer,
                    'reward_type' => ReferralRewardType::PlanTime,
                    'plan_id' => $pro->id,
                    'duration_days' => 30,
                    'if_already_on_plan' => AlreadyOnPlanBehavior::StripeBalanceCredit,
                ],
                [
                    'name' => 'A free month for the new user on their first payment',
                    'trigger' => ReferralTrigger::FirstPayment,
                    'recipient' => ReferralRecipient::Referee,
                    'reward_type' => ReferralRewardType::PlanTime,
                    'plan_id' => $pro->id,
                    'duration_days' => 30,
                    'if_already_on_plan' => AlreadyOnPlanBehavior::StripeBalanceCredit,
                ],
                ...collect([5 => 90, 10 => 180, 25 => 365])->map(fn (int $days, int $count): array => [
                    'name' => "{$count} paying referrals",
                    'trigger' => ReferralTrigger::Milestone,
                    'recipient' => ReferralRecipient::Referrer,
                    'reward_type' => ReferralRewardType::PlanTime,
                    'plan_id' => $pro->id,
                    'duration_days' => $days,
                    'milestone_count' => $count,
                    'if_already_on_plan' => AlreadyOnPlanBehavior::ConvertToCredits,
                ])->values()->all(),
            ];
        }

        foreach ($rules as $index => $rule) {
            $program->rules()->create([...$rule, 'sort_order' => $index]);
        }
    }
}
