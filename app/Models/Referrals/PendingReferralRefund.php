<?php

namespace App\Models\Referrals;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A refund (or dispute) seen before its payment was recorded: applied by
 * `ReferralLifecycle::recordPayment()` when that payment arrives.
 */
#[Fillable(['payment_intent_id', 'fully_refunded', 'reason'])]
class PendingReferralRefund extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fully_refunded' => 'boolean',
        ];
    }
}
