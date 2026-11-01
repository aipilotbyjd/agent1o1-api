<?php

namespace App\Services\Referrals;

use App\Enums\Billing\PlanGrantSource;
use App\Enums\Billing\PlanGrantStatus;
use App\Enums\Referrals\ReferralRecipient;
use App\Enums\Referrals\ReferralRewardStatus;
use App\Enums\Referrals\ReferralRewardType;
use App\Enums\Referrals\ReferralStatus;
use App\Enums\Referrals\ReferralTrigger;
use App\Models\Billing\PlanGrant;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralCode;
use App\Models\Referrals\ReferralProgram;
use App\Models\Referrals\ReferralReward;
use App\Models\Referrals\ReferralRewardRule;
use App\Models\Referrals\ReferralVisit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The numbers behind the referrer's dashboard and the admin overview.
 */
class ReferralStats
{
    public function __construct(
        private readonly ReferralSettings $settings,
        private readonly ReferralRecipientWorkspace $workspaces,
        private readonly ReferralRuleDescriber $describer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forReferrer(User $user, ReferralCode $code): array
    {
        $referrals = Referral::query()
            ->where('referrer_user_id', $user->id)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $rewards = ReferralReward::query()->where('recipient_user_id', $user->id);
        $granted = (clone $rewards)->where('status', ReferralRewardStatus::Granted);

        $workspace = $this->workspaces->forReferrer($user, $code);
        $planTime = $workspace === null ? null : PlanGrant::query()
            ->where('workspace_id', $workspace->id)
            ->where('source', PlanGrantSource::Referral)
            ->active()
            ->with('plan')
            ->orderByDesc('expires_at')
            ->first();

        return [
            'visits' => $code->visits()->count(),
            'signups' => (int) $referrals->except([ReferralStatus::Rejected->value])->sum(),
            'verified' => $this->reachedAtLeast($referrals, ReferralStatus::Verified),
            'activated' => $this->reachedAtLeast($referrals, ReferralStatus::Activated),
            'converted' => (int) ($referrals[ReferralStatus::Converted->value] ?? 0),
            'credits_earned' => (int) (clone $granted)->where('reward_type', ReferralRewardType::Credits)->sum('credits'),
            'plan_days_earned' => (int) (clone $granted)->where('reward_type', ReferralRewardType::PlanTime)->sum('duration_days'),
            'invoice_credit_cents' => (int) (clone $granted)->where('reward_type', ReferralRewardType::StripeBalanceCredit)->sum('amount_cents'),
            'pending_rewards' => (clone $rewards)->whereIn('status', [ReferralRewardStatus::Pending, ReferralRewardStatus::AwaitingApproval])->count(),
            'free_plan_time' => $planTime === null ? null : [
                'plan' => $planTime->plan?->name,
                'expires_at' => $planTime->expires_at,
            ],
        ];
    }

    /**
     * The program a user's code currently earns under.
     */
    public function programFor(ReferralCode $code): ?ReferralProgram
    {
        return $code->program?->isLive() ? $code->program : $this->settings->defaultProgram();
    }

    /**
     * @return array{count: int, remaining: int, description: string}|null
     */
    public function nextMilestone(User $user, ?ReferralProgram $program): ?array
    {
        if ($program === null) {
            return null;
        }

        $converted = Referral::query()
            ->where('referrer_user_id', $user->id)
            ->where('status', ReferralStatus::Converted)
            ->count();

        $next = $this->settings->rulesFor($program, ReferralTrigger::Milestone)
            ->filter(fn (ReferralRewardRule $rule): bool => (int) $rule->milestone_count > $converted)
            ->sortBy('milestone_count')
            ->first();

        return $next === null ? null : [
            'count' => (int) $next->milestone_count,
            'remaining' => (int) $next->milestone_count - $converted,
            'description' => $this->describer->describe($next),
        ];
    }

    /**
     * The program's live terms, in rule order.
     *
     * @return list<array{trigger: string, recipient: string, reward_type: string, description: string}>
     */
    public function terms(ReferralProgram $program): array
    {
        return collect(ReferralTrigger::ruleValues())
            ->flatMap(fn (string $trigger) => $this->settings->rulesFor($program, ReferralTrigger::from($trigger)))
            ->sortBy('sort_order')
            ->map(fn (ReferralRewardRule $rule): array => [
                'trigger' => $rule->trigger->value,
                'recipient' => $rule->recipient->value,
                'reward_type' => $rule->reward_type->value,
                'description' => $this->describer->describe($rule),
            ])
            ->values()
            ->all();
    }

    /**
     * Platform-wide numbers for the admin overview.
     *
     * @return array<string, mixed>
     */
    public function platform(int $days): array
    {
        $since = now()->subDays($days);

        $byStatus = Referral::query()
            ->where('created_at', '>=', $since)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $signups = (int) $byStatus->except([ReferralStatus::Rejected->value])->sum();
        $converted = (int) ($byStatus[ReferralStatus::Converted->value] ?? 0);

        $granted = ReferralReward::query()
            ->where('status', ReferralRewardStatus::Granted)
            ->where('granted_at', '>=', $since);

        $topReferrers = Referral::query()
            ->where('status', ReferralStatus::Converted)
            ->where('converted_at', '>=', $since)
            ->select('referrer_user_id', DB::raw('count(*) as converted'))
            ->groupBy('referrer_user_id')
            ->orderByDesc('converted')
            ->limit(10)
            ->with('referrer:id,name,email')
            ->get()
            ->map(fn (Referral $row): array => [
                'user_id' => $row->referrer_user_id,
                'name' => $row->referrer?->name,
                'email' => $row->referrer?->email,
                'converted' => (int) $row->getAttribute('converted'),
            ]);

        return [
            'window_days' => $days,
            'visits' => ReferralVisit::query()->where('created_at', '>=', $since)->count(),
            'signups' => $signups,
            'rejected' => (int) ($byStatus[ReferralStatus::Rejected->value] ?? 0),
            'by_status' => $byStatus,
            'activation_rate' => $signups === 0 ? 0 : round($this->reachedAtLeast($byStatus, ReferralStatus::Activated) / $signups, 4),
            'conversion_rate' => $signups === 0 ? 0 : round($converted / $signups, 4),
            'credits_issued' => (int) (clone $granted)->where('reward_type', ReferralRewardType::Credits)->sum('credits'),
            'plan_days_issued' => (int) (clone $granted)->where('reward_type', ReferralRewardType::PlanTime)->sum('duration_days'),
            'invoice_credit_cents_issued' => (int) (clone $granted)->where('reward_type', ReferralRewardType::StripeBalanceCredit)->sum('amount_cents'),
            'credits_clawed_back' => (int) ReferralReward::query()->where('revoked_at', '>=', $since)->sum('credits_clawed_back'),
            'rewards_awaiting_approval' => ReferralReward::query()->where('status', ReferralRewardStatus::AwaitingApproval)->count(),
            'rewards_pending' => ReferralReward::query()->where('status', ReferralRewardStatus::Pending)->count(),
            'rewards_by_recipient' => [
                ReferralRecipient::Referrer->value => (clone $granted)->where('recipient_role', ReferralRecipient::Referrer)->count(),
                ReferralRecipient::Referee->value => (clone $granted)->where('recipient_role', ReferralRecipient::Referee)->count(),
            ],
            'active_referral_plan_grants' => PlanGrant::query()
                ->where('source', PlanGrantSource::Referral)
                ->where('status', PlanGrantStatus::Active)
                ->where('expires_at', '>', now())
                ->count(),
            'top_referrers' => $topReferrers,
        ];
    }

    /**
     * @param  Collection<string, int>  $byStatus
     */
    private function reachedAtLeast($byStatus, ReferralStatus $status): int
    {
        return (int) collect(ReferralStatus::cases())
            ->filter(fn (ReferralStatus $candidate): bool => $candidate->rank() >= $status->rank())
            ->sum(fn (ReferralStatus $candidate): int => (int) ($byStatus[$candidate->value] ?? 0));
    }
}
