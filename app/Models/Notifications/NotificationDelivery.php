<?php

namespace App\Models\Notifications;

use App\Enums\Notifications\DeliveryStatus;
use App\Models\User;
use Database\Factories\Notifications\NotificationDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One email or chat/webhook message a notification produced, and how it went.
 * Integration deliveries also carry the `payload` their job sends, so a retry
 * sends exactly what the first attempt did.
 */
#[Fillable([
    'notification_id', 'workspace_id', 'notification_channel_id', 'user_id', 'event_key',
    'channel', 'target', 'status', 'attempts', 'payload', 'error', 'sent_at',
])]
class NotificationDelivery extends Model
{
    /** @use HasFactory<NotificationDeliveryFactory> */
    use HasFactory, HasUuids, MassPrunable;

    public const string CHANNEL_MAIL = 'mail';

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'attempts' => 'integer',
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function notificationChannel(): BelongsTo
    {
        return $this->belongsTo(NotificationChannel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markSent(): void
    {
        $this->forceFill([
            'status' => DeliveryStatus::Sent,
            'attempts' => $this->attempts + 1,
            'error' => null,
            'sent_at' => now(),
        ])->save();
    }

    public function markFailed(string $error, bool $final): void
    {
        $this->forceFill([
            'status' => $final ? DeliveryStatus::Failed : DeliveryStatus::Pending,
            'attempts' => $this->attempts + 1,
            'error' => Str::limit($error, 480),
        ])->save();
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays((int) config('notifications.retention.delivery_days')));
    }
}
