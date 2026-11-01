<?php

namespace App\Models\Billing;

use Database\Factories\Billing\StripeWebhookFailureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A Stripe event our handler failed on (see the migration for why this exists).
 * One row per event: a repeat failure raises `attempts`, and the row is
 * resolved when the event is finally processed, by Stripe's own retry or by a
 * super admin's replay.
 */
#[Fillable(['stripe_event_id', 'type', 'error', 'attempts', 'first_failed_at', 'last_failed_at', 'resolved_at'])]
class StripeWebhookFailure extends Model
{
    /** @use HasFactory<StripeWebhookFailureFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'first_failed_at' => 'datetime',
            'last_failed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<StripeWebhookFailure>  $query
     * @return Builder<StripeWebhookFailure>
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public static function record(string $eventId, string $type, string $error): self
    {
        $failure = self::query()->firstOrNew(['stripe_event_id' => $eventId]);

        $failure->fill([
            'type' => $type,
            'error' => Str::limit($error, 1000, ''),
            'attempts' => $failure->exists ? $failure->attempts + 1 : 1,
            'first_failed_at' => $failure->first_failed_at ?? now(),
            'last_failed_at' => now(),
            'resolved_at' => null,
        ])->save();

        return $failure;
    }

    public static function resolve(string $eventId): void
    {
        self::query()
            ->where('stripe_event_id', $eventId)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }
}
