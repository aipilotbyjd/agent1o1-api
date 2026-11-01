<?php

namespace App\Models\Billing;

use App\Enums\Billing\CreditGrantSource;
use App\Models\Workspaces\Workspace;
use Database\Factories\Billing\CreditGrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One block of credits in a workspace's wallet. `amount` is what was
 * granted and never changes; `remaining` only goes down, as credits are
 * spent, expire or are revoked. Written only through `CreditWallet`.
 */
#[Fillable([
    'workspace_id',
    'usage_period_id',
    'source',
    'amount',
    'remaining',
    'priority',
    'starts_at',
    'expires_at',
    'expired_at',
    'revoked_at',
    'revoke_reason',
    'reference_type',
    'reference_id',
    'stripe_reference',
    'idempotency_key',
    'granted_by',
    'reason',
])]
class CreditGrant extends Model
{
    /** @use HasFactory<CreditGrantFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => CreditGrantSource::class,
            'amount' => 'integer',
            'remaining' => 'integer',
            'priority' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'expired_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<UsagePeriod, $this>
     */
    public function usagePeriod(): BelongsTo
    {
        return $this->belongsTo(UsagePeriod::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<CreditGrantAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(CreditGrantAllocation::class);
    }

    /**
     * Grants that can pay for a charge right now, in the order they should
     * be spent: soonest expiry first (never-expiring last), then priority,
     * then oldest.
     *
     * @param  Builder<CreditGrant>  $query
     */
    public function scopeSpendable(Builder $query): void
    {
        $now = now();

        $query->where('remaining', '>', 0)
            ->whereNull('revoked_at')
            ->whereNull('expired_at')
            ->where('starts_at', '<=', $now)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', $now))
            ->orderByRaw('case when expires_at is null then 1 else 0 end')
            ->orderBy('expires_at')
            ->orderBy('priority')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function isSpendable(): bool
    {
        return $this->remaining > 0
            && $this->revoked_at === null
            && $this->expired_at === null
            && $this->starts_at->lte(now())
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function spent(): int
    {
        return $this->amount - $this->remaining;
    }
}
