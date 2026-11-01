<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How many credits of one charge (`CreditTransaction`) a grant paid for. A
 * charge spanning two grants has two rows, so the ledger can always say
 * "100 free + 50 top-up".
 */
#[Fillable(['credit_transaction_id', 'credit_grant_id', 'credits'])]
class CreditGrantAllocation extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credits' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CreditTransaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(CreditTransaction::class, 'credit_transaction_id');
    }

    /**
     * @return BelongsTo<CreditGrant, $this>
     */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(CreditGrant::class, 'credit_grant_id');
    }
}
