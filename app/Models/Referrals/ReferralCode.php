<?php

namespace App\Models\Referrals;

use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Referrals\ReferralCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A user's shareable `?ref=` code. `program_id` null follows whichever
 * program is the default; set, it pins the code to a program.
 */
#[Fillable([
    'user_id',
    'program_id',
    'code',
    'is_active',
    'max_uses',
    'expires_at',
    'reward_workspace_id',
    'rule_multiplier',
    'custom_code_set_at',
])]
class ReferralCode extends Model
{
    /** @use HasFactory<ReferralCodeFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'rule_multiplier' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'max_uses' => 'integer',
            'expires_at' => 'datetime',
            'rule_multiplier' => 'float',
            'custom_code_set_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(ReferralProgram::class, 'program_id');
    }

    public function rewardWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'reward_workspace_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(ReferralVisit::class);
    }

    /**
     * Active, unexpired, and under its use limit, whether a new signup can
     * be attributed to it. Rejected referrals don't count toward the limit.
     */
    public function isUsable(): bool
    {
        if (! $this->is_active || ($this->expires_at !== null && $this->expires_at->isPast())) {
            return false;
        }

        if ($this->max_uses === null) {
            return true;
        }

        return $this->referrals()->where('status', '!=', 'rejected')->count() < $this->max_uses;
    }
}
