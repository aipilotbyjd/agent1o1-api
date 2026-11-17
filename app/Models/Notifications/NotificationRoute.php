<?php

namespace App\Models\Notifications;

use App\Enums\Notifications\NotificationCategory;
use App\Enums\Notifications\NotificationType;
use App\Enums\Notifications\NotificationSeverity;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Notifications\NotificationRouteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sends a workspace's notifications to one of its chat or webhook channels:
 * one event (`event_key`), a whole category (`category`), or every event
 * when both are null, optionally only from `min_severity` up.
 *
 * Evaluated once per event, however many members were notified, so a
 * channel gets each message once.
 */
#[Fillable(['workspace_id', 'notification_channel_id', 'event_key', 'category', 'min_severity', 'created_by'])]
class NotificationRoute extends Model
{
    /** @use HasFactory<NotificationRouteFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'event_key' => NotificationType::class,
            'category' => NotificationCategory::class,
            'min_severity' => NotificationSeverity::class,
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(NotificationChannel::class, 'notification_channel_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Routes in a workspace that cover this event, before the severity check.
     *
     * @param  Builder<self>  $query
     */
    public function scopeCovering(Builder $query, NotificationType $event): void
    {
        $query->where(function (Builder $query) use ($event): void {
            $query->where('event_key', $event->value)
                ->orWhere(fn (Builder $query) => $query->whereNull('event_key')->where('category', $event->definition()->category->value))
                ->orWhere(fn (Builder $query) => $query->whereNull('event_key')->whereNull('category'));
        });
    }

    public function allowsSeverity(NotificationSeverity $severity): bool
    {
        return $this->min_severity === null || $severity->isAtLeast($this->min_severity);
    }
}
