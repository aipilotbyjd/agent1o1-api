<?php

namespace App\Models\Triggers;

use App\Enums\Triggers\ProviderEventStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One event as a provider sent it, kept just long enough to be routed and, if
 * needed, replayed (`triggers.provider_events_retention_days`).
 *
 * The provider's `event_id` is unique per provider, so a delivery the provider
 * retries is stored once.
 */
#[Fillable([
    'provider', 'event_id', 'event_type', 'routing_key', 'payload', 'status',
    'error', 'received_at', 'processed_at',
])]
class ProviderEvent extends Model
{
    use HasUuids;

    /**
     * Defaults for a new event.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'received',
    ];

    /**
     * The attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => ProviderEventStatus::class,
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
