<?php

namespace App\Models\Notifications;

use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Notifications\NotificationChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A Slack, Discord or webhook destination a workspace sends notifications
 * to. Which events reach it is decided by its `routes`.
 *
 * Generic webhooks are signed with `signing_secret`
 * (`X-Notification-Signature`), generated when the channel is created. A
 * channel that keeps failing is switched off (`disabled_at`) after
 * `notifications.integrations.disable_after_failures` failures in a row.
 */
#[Fillable(['workspace_id', 'created_by', 'type', 'name', 'config', 'is_active', 'signing_secret'])]
#[Hidden(['config', 'signing_secret'])]
class NotificationChannel extends Model
{
    /** @use HasFactory<NotificationChannelFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::creating(function (self $channel): void {
            $channel->signing_secret ??= self::newSigningSecret();
        });
    }

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
            'signing_secret' => 'encrypted',
            'consecutive_failures' => 'integer',
            'last_delivered_at' => 'datetime',
            'last_failed_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    public static function newSigningSecret(): string
    {
        return 'whsec_'.Str::random(40);
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<NotificationRoute, $this>
     */
    public function routes(): HasMany
    {
        return $this->hasMany(NotificationRoute::class);
    }

    /**
     * @return HasMany<NotificationDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    public function recordDelivered(): void
    {
        $this->forceFill([
            'consecutive_failures' => 0,
            'last_delivered_at' => now(),
        ])->save();
    }

    /**
     * Counts a failure and reports whether this one switched the channel off.
     */
    public function recordFailure(string $error): bool
    {
        $this->forceFill([
            'consecutive_failures' => $this->consecutive_failures + 1,
            'last_failed_at' => now(),
            'last_error' => Str::limit($error, 480),
        ]);

        $threshold = (int) config('notifications.integrations.disable_after_failures');
        $switchedOff = $this->is_active && $threshold > 0 && $this->consecutive_failures >= $threshold;

        if ($switchedOff) {
            $this->forceFill(['is_active' => false, 'disabled_at' => now()]);
        }

        $this->save();

        return $switchedOff;
    }
}
