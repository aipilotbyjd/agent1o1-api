<?php

namespace App\Models\Billing;

use App\Models\Workspaces\Workspace;
use App\Services\Billing\CreditWallet;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `credits_used` and the two `overage_*` counters are engine-managed
 * (incremented by `DeductCreditsAction`/`BillOverageCreditsAction` via
 * `increment()`, never mass-assigned), only the window bounds and an
 * optional limit are user/system-set at creation time.
 */
#[Fillable(['workspace_id', 'plan_id', 'subscription_id', 'starts_at', 'ends_at', 'credits_limit'])]
class UsagePeriod extends Model
{
    use HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'credits_used' => 0,
        'overage_credits_used' => 0,
        'overage_credits_billed' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'credits_used' => 'integer',
            'credits_limit' => 'integer',
            'overage_credits_used' => 'integer',
            'overage_credits_billed' => 'integer',
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

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * How much of this period's allowance grant is left, ignoring any
     * carry-over credits the workspace holds. `0` once the allowance is
     * spent or the period has ended; meaningless (and not called) on an
     * unlimited period, where `credits_limit` is `null`.
     */
    public function remainingPlanCredits(): int
    {
        if ($this->credits_limit === null) {
            return 0;
        }

        $grant = app(CreditWallet::class)->ensureAllowance($this);

        return $grant !== null && $grant->isSpendable() ? $grant->remaining : 0;
    }

    /**
     * Overage this period that hasn't been invoiced yet: what
     * `BillOverageCreditsAction` turns into a Stripe invoice line. Charges
     * only ever add to `overage_credits_used` and billing only ever adds to
     * `overage_credits_billed`, so this can't go negative in practice; the
     * clamp guards a period whose counters were adjusted by hand.
     */
    public function unbilledOverageCredits(): int
    {
        return max(0, $this->overage_credits_used - $this->overage_credits_billed);
    }
}
