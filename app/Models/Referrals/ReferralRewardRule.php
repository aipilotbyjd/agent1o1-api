<?php

namespace App\Models\Referrals;

use App\Enums\Referrals\AlreadyOnPlanBehavior;
use App\Enums\Referrals\ReferralRecipient;
use App\Enums\Referrals\ReferralRewardType;
use App\Enums\Referrals\ReferralTrigger;
use App\Models\Billing\Plan;
use App\Services\Referrals\ReferralSettings;
use App\Services\Referrals\TriggerContext;
use Database\Factories\Referrals\ReferralRewardRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * "When `trigger` happens, give `reward_type` to `recipient`." Which value
 * columns matter depends on the type:
 *
 * - `credits`: `credits_amount`
 * - `plan_time`: `plan_id` + `duration_days` (+ `if_already_on_plan`)
 * - `stripe_balance_credit`: `amount_cents`, or `amount_percent_of_plan` of
 *   `plan_id`'s monthly price
 * - `trial_extension`: `trial_days` (referee only)
 *
 * `conditions` keys, all optional: `min_payment_cents`, `plan_ids`,
 * `billing_intervals`, `payment_sources`, `nth_payment_min`,
 * `nth_payment_max`.
 */
#[Fillable([
    'program_id',
    'name',
    'description',
    'is_active',
    'sort_order',
    'trigger',
    'recipient',
    'reward_type',
    'credits_amount',
    'plan_id',
    'duration_days',
    'amount_cents',
    'amount_percent_of_plan',
    'trial_days',
    'milestone_count',
    'hold_days',
    'if_already_on_plan',
    'fallback_credits',
    'max_per_recipient',
    'conditions',
    'starts_at',
    'ends_at',
])]
class ReferralRewardRule extends Model
{
    /** @use HasFactory<ReferralRewardRuleFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
        'if_already_on_plan' => AlreadyOnPlanBehavior::GrantAnyway,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'trigger' => ReferralTrigger::class,
            'recipient' => ReferralRecipient::class,
            'reward_type' => ReferralRewardType::class,
            'credits_amount' => 'integer',
            'duration_days' => 'integer',
            'amount_cents' => 'integer',
            'amount_percent_of_plan' => 'integer',
            'trial_days' => 'integer',
            'milestone_count' => 'integer',
            'hold_days' => 'integer',
            'if_already_on_plan' => AlreadyOnPlanBehavior::class,
            'fallback_credits' => 'integer',
            'max_per_recipient' => 'integer',
            'conditions' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => ReferralSettings::flush());
        static::deleted(fn () => ReferralSettings::flush());
        static::restored(fn () => ReferralSettings::flush());
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(ReferralProgram::class, 'program_id');
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isLive(): bool
    {
        return $this->is_active
            && ! $this->trashed()
            && ($this->starts_at === null || $this->starts_at->isPast())
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    /**
     * Whether this rule's `conditions` accept the event described by
     * `$context`. A condition that needs payment details the context lacks
     * (e.g. `min_payment_cents` on a signup) fails closed.
     */
    public function matches(TriggerContext $context): bool
    {
        $conditions = $this->conditions ?? [];

        if (isset($conditions['min_payment_cents']) && ($context->paymentCents ?? 0) < (int) $conditions['min_payment_cents']) {
            return false;
        }

        if (! empty($conditions['plan_ids']) && ! in_array($context->planId, (array) $conditions['plan_ids'], true)) {
            return false;
        }

        if (! empty($conditions['billing_intervals']) && ! in_array($context->interval?->value, (array) $conditions['billing_intervals'], true)) {
            return false;
        }

        if (! empty($conditions['payment_sources']) && ! in_array($context->paymentSource?->value, (array) $conditions['payment_sources'], true)) {
            return false;
        }

        if (isset($conditions['nth_payment_min']) && ($context->paymentSequence ?? 0) < (int) $conditions['nth_payment_min']) {
            return false;
        }

        if (isset($conditions['nth_payment_max']) && ($context->paymentSequence ?? PHP_INT_MAX) > (int) $conditions['nth_payment_max']) {
            return false;
        }

        return true;
    }

    /**
     * The rule as it stood when a reward was earned: stored on the reward
     * so later edits never rewrite history.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return $this->only([
            'id',
            'name',
            'trigger',
            'recipient',
            'reward_type',
            'credits_amount',
            'plan_id',
            'duration_days',
            'amount_cents',
            'amount_percent_of_plan',
            'trial_days',
            'milestone_count',
            'hold_days',
            'if_already_on_plan',
            'fallback_credits',
            'max_per_recipient',
            'conditions',
        ]);
    }
}
