<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Dedupes inbound Stripe webhook deliveries by event ID: Stripe retries
 * delivery, so `StripeWebhookController` checks this before processing.
 */
#[Fillable(['stripe_event_id', 'type', 'processed_at'])]
class ProcessedWebhookEvent extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }
}
