<?php

namespace App\Services\Referrals;

use App\Actions\Referrals\GrantReferralRewardAction;
use App\Actions\Referrals\RevokeReferralRewardAction;
use App\Enums\Referrals\ReferralRecipient;
use App\Enums\Referrals\ReferralRewardStatus;
use App\Enums\Referrals\ReferralStatus;
use App\Enums\Referrals\ReferralTrigger;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralPayment;
use App\Models\Referrals\ReferralProgram;
use App\Models\Referrals\ReferralReward;
use App\Models\Referrals\ReferralRewardRule;
use App\Notifications\Referrals\ReferralMilestoneReachedNotification;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Decides and records what a referral event earns. For each live rule of the
 * referral's program on that trigger, it checks the rule's conditions and
 * per-recipient limit, works out the reward's value, and writes it to the
 * ledger: granting it immediately, holding it until `grant_after`, or
 * parking it for an admin's approval.
 *
 * Every reward carries an idempotency key, so a replayed webhook or a
 * listener that fires twice can never earn the same reward twice.
 */
class ReferralRuleEngine
{
    public function __construct(
        private readonly ReferralSettings $settings,
        private readonly ReferralRewardCalculator $calculator,
        private readonly ReferralRecipientWorkspace $workspaces,
        private readonly GrantReferralRewardAction $grant,
        private readonly NotificationDispatcher $notifications,
    ) {}

    /**
     * @return Collection<int, ReferralReward>
     */
    public function fire(Referral $referral, ReferralTrigger $trigger, TriggerContext $context = new TriggerContext): Collection
    {
        $program = $this->liveProgramFor($referral);

        if ($program === null || in_array($trigger, [ReferralTrigger::Milestone, ReferralTrigger::Manual], true)) {
            return collect();
        }

        return $this->settings->rulesFor($program, $trigger)
            ->filter(fn (ReferralRewardRule $rule): bool => $rule->matches($context))
            ->map(fn (ReferralRewardRule $rule): ?ReferralReward => $this->award($program, $rule, $referral, $context, $this->keyFor($rule, $referral, $context)))
            ->filter()
            ->values();
    }

    /**
     * Awards every milestone the referrer has now reached. Counted across
     * all of the referrer's converted referrals; the rules come from the
     * program of the referral that just converted. Keyed per rule and
     * referrer, so each milestone pays out once.
     *
     * @return Collection<int, ReferralReward>
     */
    public function evaluateMilestones(Referral $referral): Collection
    {
        $program = $this->liveProgramFor($referral);

        if ($program === null) {
            return collect();
        }

        $converted = Referral::query()
            ->where('referrer_user_id', $referral->referrer_user_id)
            ->where('status', ReferralStatus::Converted)
            ->count();

        $context = new TriggerContext(convertedReferrals: $converted);

        $rewards = $this->settings->rulesFor($program, ReferralTrigger::Milestone)
            ->filter(fn (ReferralRewardRule $rule): bool => $rule->milestone_count !== null && $converted >= $rule->milestone_count)
            ->map(fn (ReferralRewardRule $rule): ?ReferralReward => $this->award(
                $program,
                $rule,
                $referral,
                $context,
                "rule:{$rule->id}:user:{$referral->referrer_user_id}",
            ))
            ->filter()
            ->values();

        $workspace = $this->workspaces->for($referral, ReferralRecipient::Referrer);

        if ($rewards->isNotEmpty() && $workspace !== null && $referral->referrer !== null) {
            $this->notifications->dispatch([$referral->referrer], new ReferralMilestoneReachedNotification($workspace, $converted));
        }

        return $rewards;
    }

    private function liveProgramFor(Referral $referral): ?ReferralProgram
    {
        if (! $this->settings->enabled() || $referral->isRejected()) {
            return null;
        }

        $program = $referral->program;

        return $program?->isLive() ? $program : null;
    }

    private function award(ReferralProgram $program, ReferralRewardRule $rule, Referral $referral, TriggerContext $context, string $key): ?ReferralReward
    {
        $recipient = $rule->recipient === ReferralRecipient::Referrer ? $referral->referrer : $referral->referredUser;

        if ($recipient === null || ReferralReward::query()->where('idempotency_key', $key)->exists()) {
            return null;
        }

        $workspace = $this->workspaces->for($referral, $rule->recipient);
        $values = $this->calculator->calculate($program, $rule, $recipient, $workspace, $referral->code?->rule_multiplier ?? 1.0);

        if ($values === null) {
            return null;
        }

        $holdDays = $program->holdDaysFor($rule->trigger, $rule->hold_days);

        // The limit check and the insert run under the rule's row lock:
        // checked separately, concurrent firings each see room for one more.
        $reward = DB::transaction(function () use ($rule, $referral, $recipient, $program, $context, $key, $workspace, $values, $holdDays): ?ReferralReward {
            ReferralRewardRule::query()->whereKey($rule->id)->lockForUpdate()->first();

            if ($this->limitReached($rule, $referral, $recipient->id)) {
                return null;
            }

            try {
                return DB::transaction(fn (): ReferralReward => ReferralReward::query()->create([
                    'referral_id' => $referral->id,
                    'rule_id' => $rule->id,
                    'rule_snapshot' => [
                        ...$rule->snapshot(),
                        'program_id' => $program->id,
                        'caps' => $program->capsSnapshot(),
                    ],
                    'recipient_user_id' => $recipient->id,
                    'workspace_id' => $workspace?->id,
                    'recipient_role' => $rule->recipient,
                    'trigger' => $rule->trigger,
                    'reward_type' => $values['reward_type'],
                    'credits' => $values['credits'],
                    'plan_id' => $values['plan_id'],
                    'duration_days' => $values['duration_days'],
                    'amount_cents' => $values['amount_cents'],
                    'trial_days' => $values['trial_days'],
                    'notes' => $values['notes'],
                    'status' => $program->requiresManualApproval() ? ReferralRewardStatus::AwaitingApproval : ReferralRewardStatus::Pending,
                    'grant_after' => now()->addDays($holdDays),
                    'payment_reference' => $context->paymentReference,
                    'idempotency_key' => $key,
                ]));
            } catch (UniqueConstraintViolationException) {
                return null;
            }
        });

        if ($reward === null) {
            return null;
        }

        // A refund recorded while this reward was being created has already
        // looked for rewards to revoke and missed this one.
        if ($context->paymentReference !== null && ReferralPayment::query()->where('reference', $context->paymentReference)->whereNotNull('refunded_at')->exists()) {
            app(RevokeReferralRewardAction::class)->execute($reward, 'Payment refunded');

            return null;
        }

        if ($reward->status === ReferralRewardStatus::Pending && $holdDays === 0) {
            $this->grant->execute($reward);
        }

        return $reward;
    }

    /**
     * `max_per_recipient` caps how often a rule rewards one person: per
     * referral for referral-scoped triggers (e.g. "the first 3 payments"),
     * overall for milestones.
     */
    private function limitReached(ReferralRewardRule $rule, Referral $referral, string $recipientId): bool
    {
        if ($rule->max_per_recipient === null) {
            return false;
        }

        return ReferralReward::query()
            ->notRevoked()
            ->where('rule_id', $rule->id)
            ->where('recipient_user_id', $recipientId)
            ->when($rule->trigger !== ReferralTrigger::Milestone, fn ($query) => $query->where('referral_id', $referral->id))
            ->count() >= $rule->max_per_recipient;
    }

    private function keyFor(ReferralRewardRule $rule, Referral $referral, TriggerContext $context): string
    {
        $key = "rule:{$rule->id}:referral:{$referral->id}";

        return $rule->trigger === ReferralTrigger::RepeatPayment
            ? "{$key}:payment:{$context->paymentReference}"
            : $key;
    }
}
