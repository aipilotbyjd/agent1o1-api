<?php

namespace App\Models\Billing;

use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\CancellationOffer;
use App\Enums\Billing\CancellationOutcome;
use App\Enums\Billing\CancellationReason;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Billing\SubscriptionCancellationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One trip through the cancellation flow: the reason given, the offer that
 * was shown for it, and whether the customer left or stayed. `reason` is null
 * for a cancellation that arrived without one (an older client).
 */
#[Fillable([
    'workspace_id', 'user_id', 'plan_id', 'interval', 'reason', 'comment', 'offer', 'outcome',
    'discount_percent', 'discount_months', 'months_subscribed',
])]
class SubscriptionCancellation extends Model
{
    /** @use HasFactory<SubscriptionCancellationFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interval' => BillingInterval::class,
            'reason' => CancellationReason::class,
            'offer' => CancellationOffer::class,
            'outcome' => CancellationOutcome::class,
            'discount_percent' => 'integer',
            'discount_months' => 'integer',
            'months_subscribed' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
