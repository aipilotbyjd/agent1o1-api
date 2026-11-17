<?php

namespace App\Notifications\Channels;

use App\Events\Notifications\InboxNotificationChanged;
use App\Models\Notifications\InboxNotification;
use App\Notifications\AppNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Ramsey\Uuid\Uuid;

/**
 * Writes a notification into a member's inbox and pushes it to their open
 * apps. Replaces Laravel's `database` and `broadcast` channels for
 * `AppNotification`s, so the row and the push can never disagree.
 *
 * - An idempotent notification gets a deterministic id per member, so a
 *   repeat collides with the row it already wrote and is dropped.
 * - A grouped notification that repeats while the member's earlier one is
 *   still unread bumps that row's `group_count` and moves it back to the top
 *   instead of adding another row.
 */
class InboxChannel
{
    public function send(object $notifiable, AppNotification $notification): void
    {
        if (! $notifiable instanceof Model) {
            return;
        }

        $id = $this->idFor($notifiable, $notification);

        if (InboxNotification::query()->whereKey($id)->exists()) {
            return;
        }

        if ($this->foldIntoGroup($notifiable, $notification)) {
            return;
        }

        $definition = $notification->event->definition();

        try {
            $row = InboxNotification::query()->forceCreate([
                'id' => $id,
                'type' => $notification::class,
                'notifiable_type' => $notifiable->getMorphClass(),
                'notifiable_id' => $notifiable->getKey(),
                'workspace_id' => $notification->workspaceId,
                'event_key' => $notification->event->value,
                'category' => $definition->category->value,
                'severity' => $notification->severity()->value,
                'group_key' => $notification->groupKey(),
                'group_count' => 1,
                'data' => $notification->toInbox($notifiable),
                'read_at' => null,
                'occurred_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return;
        }

        InboxNotificationChanged::dispatch($notifiable, $row, false);
    }

    private function idFor(Model $notifiable, AppNotification $notification): string
    {
        $key = $notification->idempotencyKey();

        if ($key === null) {
            return $notification->id ?? Uuid::uuid4()->toString();
        }

        return Uuid::uuid5(Uuid::NAMESPACE_URL, "notification:{$key}:{$notifiable->getMorphClass()}:{$notifiable->getKey()}")->toString();
    }

    private function foldIntoGroup(Model $notifiable, AppNotification $notification): bool
    {
        $groupKey = $notification->groupKey();
        $grouping = $notification->event->definition()->grouping;

        if ($groupKey === null || $grouping === null) {
            return false;
        }

        /** @var InboxNotification|null $existing */
        $existing = InboxNotification::query()
            ->where('notifiable_type', $notifiable->getMorphClass())
            ->where('notifiable_id', $notifiable->getKey())
            ->where('group_key', $groupKey)
            ->whereNull('read_at')
            ->whereNull('archived_at')
            ->where('occurred_at', '>=', now()->subMinutes($grouping->windowMinutes))
            ->newestFirst()
            ->first();

        if ($existing === null) {
            return false;
        }

        $existing->forceFill([
            'data' => $notification->toInbox($notifiable),
            'severity' => $notification->severity()->value,
            'group_count' => $existing->group_count + 1,
            'occurred_at' => now(),
        ])->save();

        InboxNotificationChanged::dispatch($notifiable, $existing, true);

        return true;
    }
}
