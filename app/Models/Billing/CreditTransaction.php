<?php

namespace App\Models\Billing;

use App\Enums\Billing\CreditTransactionType;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One append-only ledger row, never updated after creation. Written only
 * via `Actions\Billing\DeductCreditsAction`. `(source_type, source_id)` is
 * unique, so a given `NodeRun`/`AgentMessage` can only ever be billed once
 * however many times a queued charge is replayed.
 *
 * `credits` is the whole charge; `topup_credits` and `overage_credits` say
 * how much of it came out of the non-expiring top-up pool and out of overage
 * respectively, so the remainder is what the plan allowance covered.
 * `debt_credits` is the part no grant could pay for (an overdraft), and
 * `allocations` say exactly which wallet grants paid for the rest.
 */
#[Fillable(['workspace_id', 'usage_period_id', 'source_type', 'source_id', 'credits', 'topup_credits', 'overage_credits', 'debt_credits', 'revenue_usd', 'reason'])]
class CreditTransaction extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => CreditTransactionType::class,
            'credits' => 'integer',
            'topup_credits' => 'integer',
            'overage_credits' => 'integer',
            'debt_credits' => 'integer',
            'revenue_usd' => 'float',
        ];
    }

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

    /**
     * @return HasMany<CreditGrantAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(CreditGrantAllocation::class);
    }
}
