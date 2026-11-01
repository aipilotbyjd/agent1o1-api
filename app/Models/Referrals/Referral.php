<?php

namespace App\Models\Referrals;

use App\Enums\Referrals\ReferralStatus;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Referrals\ReferralFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One referred user, and how far they have got. See `ReferralLifecycle`
 * for the transitions.
 */
#[Fillable([
    'program_id',
    'referral_code_id',
    'referrer_user_id',
    'referred_user_id',
    'referred_workspace_id',
    'visit_id',
    'status',
    'verified_at',
    'activated_at',
    'converted_at',
    'rejected_at',
    'rejection_reason',
    'signup_ip_hash',
])]
class Referral extends Model
{
    /** @use HasFactory<ReferralFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ReferralStatus::Pending,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReferralStatus::class,
            'verified_at' => 'datetime',
            'activated_at' => 'datetime',
            'converted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(ReferralProgram::class, 'program_id')->withTrashed();
    }

    public function code(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class, 'referral_code_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function referredWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'referred_workspace_id');
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(ReferralVisit::class, 'visit_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(ReferralReward::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ReferralPayment::class);
    }

    public function isRejected(): bool
    {
        return $this->status === ReferralStatus::Rejected;
    }

    /**
     * Whether the referral has already reached (or passed) `$status`.
     */
    public function hasReached(ReferralStatus $status): bool
    {
        return ! $this->isRejected() && $this->status->rank() >= $status->rank();
    }
}
