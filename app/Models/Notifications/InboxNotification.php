<?php

namespace App\Models\Notifications;

use App\Enums\Notifications\NotificationCategory;
use App\Enums\Notifications\NotificationEvent;
use App\Enums\Notifications\NotificationSeverity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Notifications\DatabaseNotification;

/**
 * A notification in a member's inbox: Laravel's database notification plus
 * the columns the inbox filters, sorts and groups on (`workspace_id`,
 * `event_key`, `category`, `severity`, `group_key`, `group_count`,
 * `occurred_at`, `archived_at`).
 *
 * Rows are pruned once they are old enough to be noise
 * (`config/notifications.php` → `retention`).
 *
 * @property string|null $workspace_id
 * @property string|null $event_key
 * @property string|null $category
 * @property string|null $severity
 * @property string|null $group_key
 * @property int $group_count
 * @property \Illuminate\Support\Carbon|null $occurred_at
 * @property \Illuminate\Support\Carbon|null $archived_at
 */
class InboxNotification extends DatabaseNotification
{
    use MassPrunable;

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'group_count' => 'integer',
            'occurred_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function event(): ?NotificationEvent
    {
        $key = $this->event_key ?? ($this->data['type'] ?? null);

        return is_string($key) ? NotificationEvent::tryFrom($key) : null;
    }

    public function resolvedCategory(): ?NotificationCategory
    {
        return NotificationCategory::tryFrom((string) $this->category) ?? $this->event()?->definition()->category;
    }

    public function resolvedSeverity(): NotificationSeverity
    {
        return NotificationSeverity::tryFrom((string) $this->severity)
            ?? $this->event()?->definition()->severity
            ?? NotificationSeverity::Info;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeInInbox(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeArchived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    /**
     * Notifications that ask someone to decide or fix something.
     *
     * @param  Builder<self>  $query
     */
    public function scopeNeedingAction(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->whereIn('event_key', NotificationEvent::needingAction())
                ->orWhere('severity', NotificationSeverity::Critical->value);
        });
    }

    /**
     * Newest first, by the latest occurrence of a grouped notification.
     *
     * @param  Builder<self>  $query
     */
    public function scopeNewestFirst(Builder $query): void
    {
        $query->orderByDesc('occurred_at')->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::query()->where(function (Builder $query): void {
            $query->where(function (Builder $query): void {
                $query->whereNotNull('read_at')
                    ->where('occurred_at', '<', now()->subDays((int) config('notifications.retention.read_days')));
            })->orWhere('occurred_at', '<', now()->subDays((int) config('notifications.retention.unread_days')));
        });
    }
}
