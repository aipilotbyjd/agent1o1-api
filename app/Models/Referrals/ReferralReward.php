<?php

namespace App\Models\Referrals;

use App\Enums\Referrals\ReferralRecipient;
use App\Enums\Referrals\ReferralRewardStatus;
use App\Enums\Referrals\ReferralRewardType;
use App\Enums\Referrals\ReferralTrigger;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Referrals\ReferralRewardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry on the referral ledger. The value columns (`credits`,
 * `duration_days`, ...) hold what this reward actually gives, after the
 * code's multiplier and any already-on-plan conversion; `rule_snapshot`
 * holds the rule it came from as it stood at the time.
 */
#[Fillable([
    'referral_id',
    'rule_id',
    'rule_snapshot',
    'recipient_user_id',
    'workspace_id',
    'recipient_role',
    'trigger',
    'reward_type',
    'credits',
    'plan_id',
    'duration_days',
    'amount_cents',
    'trial_days',
    'status',
    'grant_after',
    'granted_at',
    'revoked_at',
    'revoked_reason',
    'plan_grant_id',
    'stripe_balance_transaction_id',
    'credits_clawed_back',
    'payment_reference',
    'granted_by',
    'notes',
    'idempotency_key',
])]
class ReferralReward extends Model
{
    /** @use HasFactory<ReferralRewardFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'credits_clawed_back' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rule_snapshot' => 'array',
            'recipient_role' => ReferralRecipient::class,
            'trigger' => ReferralTrigger::class,
            'reward_type' => ReferralRewardType::class,
            'credits' => 'integer',
            'duration_days' => 'integer',
            'amount_cents' => 'integer',
            'trial_days' => 'integer',
            'status' => ReferralRewardStatus::class,
            'grant_after' => 'datetime',
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'credits_clawed_back' => 'integer',
        ];
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ReferralRewardRule::class, 'rule_id')->withTrashed();
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function planGrant(): BelongsTo
    {
        return $this->belongsTo(PlanGrant::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'granted_by');
    }

    /**
     * Rewards that count toward caps and limits: everything not revoked.
     *
     * @param  Builder<ReferralReward>  $query
     * @return Builder<ReferralReward>
     */
    public function scopeNotRevoked(Builder $query): Builder
    {
        return $query->where('status', '!=', ReferralRewardStatus::Revoked);
    }

    /**
     * @param  Builder<ReferralReward>  $query
     * @return Builder<ReferralReward>
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', ReferralRewardStatus::Pending)
            ->where(fn (Builder $query) => $query->whereNull('grant_after')->orWhere('grant_after', '<=', now()));
    }

    /**
     * The program caps frozen into this reward when it was earned.
     */
    public function cap(string $key): ?int
    {
        $value = $this->rule_snapshot['caps'][$key] ?? null;

        return $value === null ? null : (int) $value;
    }
}
