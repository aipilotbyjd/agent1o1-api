<?php

namespace App\Models\Referrals;

use App\Enums\Referrals\ReferralActivationEvent;
use App\Enums\Referrals\ReferralApprovalMode;
use App\Enums\Referrals\ReferralTrigger;
use App\Services\Referrals\ReferralSettings;
use Database\Factories\Referrals\ReferralProgramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Every tunable setting of one referral program: what the admin API edits.
 * Saving one clears the cached settings (`ReferralSettings`), so a change
 * applies to the very next request.
 */
#[Fillable([
    'name',
    'slug',
    'description',
    'is_active',
    'is_default',
    'starts_at',
    'ends_at',
    'attribution_window_days',
    'claim_window_hours',
    'require_verified_email',
    'activation_event',
    'activation_min_count',
    'activation_window_days',
    'default_hold_days',
    'approval_mode',
    'revoke_on_partial_refund',
    'referrer_monthly_credit_cap',
    'referrer_max_stacked_plan_days',
    'referrer_max_referrals_per_month',
    'referrer_min_account_age_days',
    'referrer_eligible_plan_ids',
    'fraud_checks',
])]
class ReferralProgram extends Model
{
    /** @use HasFactory<ReferralProgramFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * Every fraud check and its default tuning. A program's own
     * `fraud_checks` is merged over this, so a check added later is on (or
     * off) for existing programs without a data migration. Self-referral is
     * not listed: it is always refused.
     *
     * @var array<string, mixed>
     */
    public const array DEFAULT_FRAUD_CHECKS = [
        'shared_workspace' => true,
        'same_email_domain' => false,
        'disposable_email' => true,
        'card_fingerprint' => true,
        'ip_velocity' => ['enabled' => true, 'max' => 5, 'hours' => 24],
        'velocity_alert' => ['enabled' => true, 'max_per_hour' => 20],
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'is_default' => false,
        'attribution_window_days' => 60,
        'claim_window_hours' => 24,
        'require_verified_email' => true,
        'activation_event' => ReferralActivationEvent::RunOrAgentSession,
        'activation_min_count' => 1,
        'activation_window_days' => 14,
        'default_hold_days' => 14,
        'approval_mode' => ReferralApprovalMode::Automatic,
        'revoke_on_partial_refund' => false,
        'referrer_min_account_age_days' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'attribution_window_days' => 'integer',
            'claim_window_hours' => 'integer',
            'require_verified_email' => 'boolean',
            'activation_event' => ReferralActivationEvent::class,
            'activation_min_count' => 'integer',
            'activation_window_days' => 'integer',
            'default_hold_days' => 'integer',
            'approval_mode' => ReferralApprovalMode::class,
            'revoke_on_partial_refund' => 'boolean',
            'referrer_monthly_credit_cap' => 'integer',
            'referrer_max_stacked_plan_days' => 'integer',
            'referrer_max_referrals_per_month' => 'integer',
            'referrer_min_account_age_days' => 'integer',
            'referrer_eligible_plan_ids' => 'array',
            'fraud_checks' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Exactly one default: promoting a program demotes whichever held it.
        static::saved(function (ReferralProgram $program): void {
            if ($program->is_default && ($program->wasRecentlyCreated || $program->wasChanged('is_default'))) {
                static::query()
                    ->whereKeyNot($program->getKey())
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            ReferralSettings::flush();
        });

        static::deleted(fn () => ReferralSettings::flush());
        static::restored(fn () => ReferralSettings::flush());
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ReferralRewardRule::class, 'program_id')->orderBy('sort_order');
    }

    public function codes(): HasMany
    {
        return $this->hasMany(ReferralCode::class, 'program_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'program_id');
    }

    /**
     * Switched on, not deleted, and inside its date window. A program that
     * isn't live attributes no new referrals and grants no new rewards:
     * the lever for pausing a campaign.
     */
    public function isLive(): bool
    {
        return $this->is_active
            && ! $this->trashed()
            && ($this->starts_at === null || $this->starts_at->isPast())
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    /**
     * @param  Builder<ReferralProgram>  $query
     * @return Builder<ReferralProgram>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /**
     * One fraud check's setting, with the program's own value merged over
     * the default. A boolean check returns its switch; a tunable check
     * returns its array (with an `enabled` key).
     */
    public function fraudCheck(string $key): mixed
    {
        $default = self::DEFAULT_FRAUD_CHECKS[$key] ?? null;
        $own = ($this->fraud_checks ?? [])[$key] ?? null;

        if (is_array($default)) {
            return array_merge($default, is_array($own) ? $own : []);
        }

        return $own ?? $default;
    }

    public function fraudCheckEnabled(string $key): bool
    {
        $setting = $this->fraudCheck($key);

        return is_array($setting) ? (bool) ($setting['enabled'] ?? false) : (bool) $setting;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedFraudChecks(): array
    {
        return collect(self::DEFAULT_FRAUD_CHECKS)
            ->mapWithKeys(fn (mixed $default, string $key): array => [$key => $this->fraudCheck($key)])
            ->all();
    }

    /**
     * Days a reward from `$trigger` waits before it is granted: the rule's
     * own `hold_days` when set, else the program default for payment-based
     * triggers and none for the rest.
     */
    public function holdDaysFor(ReferralTrigger $trigger, ?int $ruleHoldDays): int
    {
        if ($ruleHoldDays !== null) {
            return $ruleHoldDays;
        }

        return $trigger->isPaymentBased() ? $this->default_hold_days : 0;
    }

    public function requiresManualApproval(): bool
    {
        return $this->approval_mode === ReferralApprovalMode::Manual;
    }

    /**
     * The caps the reward handlers enforce at grant time, frozen into each
     * reward's snapshot.
     *
     * @return array{referrer_monthly_credit_cap: ?int, referrer_max_stacked_plan_days: ?int}
     */
    public function capsSnapshot(): array
    {
        return [
            'referrer_monthly_credit_cap' => $this->referrer_monthly_credit_cap,
            'referrer_max_stacked_plan_days' => $this->referrer_max_stacked_plan_days,
        ];
    }
}
