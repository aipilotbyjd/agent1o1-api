<?php

namespace App\Models\Billing;

use App\Enums\Billing\PlanGrantSource;
use App\Enums\Billing\PlanGrantStatus;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Billing\PlanGrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plan entitlement held outside Cashier: a lifetime purchase, or a comped
 * grant. `Workspace::currentPlan()` weighs the active grant's plan against
 * any active subscription's, so this is a genuine second source of truth for
 * what a workspace is allowed to do.
 */
#[Fillable([
    'workspace_id',
    'plan_id',
    'purchased_by',
    'source',
    'status',
    'price_cents',
    'currency',
    'stripe_checkout_session_id',
    'stripe_payment_intent_id',
    'granted_at',
    'expires_at',
    'expiry_notified_at',
])]
class PlanGrant extends Model
{
    /** @use HasFactory<PlanGrantFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => PlanGrantStatus::Pending,
        'source' => PlanGrantSource::LifetimePurchase,
        'currency' => 'usd',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PlanGrantStatus::class,
            'source' => PlanGrantSource::class,
            'price_cents' => 'integer',
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
            'expiry_notified_at' => 'datetime',
        ];
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

    public function purchasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchased_by');
    }

    /**
     * Paid for (or comped) and not since revoked or lapsed. A null
     * `expires_at` never lapses, which is every lifetime purchase.
     */
    public function isActive(): bool
    {
        return $this->status === PlanGrantStatus::Active
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * @param  Builder<PlanGrant>  $query
     * @return Builder<PlanGrant>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', PlanGrantStatus::Active)
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}
