<?php

namespace App\Models\Billing;

use App\Enums\Billing\BillingInterval;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Stripe price a plan has sold on. Rows are never deleted: a retired price
 * still has subscribers paying it, and the webhook has to map it back to
 * their plan.
 */
#[Fillable(['plan_id', 'interval', 'stripe_price_id', 'amount_cents', 'retired_at', 'created_by'])]
class PlanPriceHistory extends Model
{
    use HasUuids;

    protected $table = 'plan_price_history';

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interval' => BillingInterval::class,
            'amount_cents' => 'integer',
            'retired_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
