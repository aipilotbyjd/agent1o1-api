<?php

namespace App\Models\Referrals;

use App\Enums\Billing\BillingInterval;
use App\Enums\Referrals\ReferralPaymentSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'referral_id',
    'reference',
    'payment_intent_id',
    'source',
    'amount_cents',
    'currency',
    'plan_id',
    'billing_interval',
    'sequence',
    'refunded_at',
])]
class ReferralPayment extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => ReferralPaymentSource::class,
            'amount_cents' => 'integer',
            'billing_interval' => BillingInterval::class,
            'sequence' => 'integer',
            'refunded_at' => 'datetime',
        ];
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }
}
