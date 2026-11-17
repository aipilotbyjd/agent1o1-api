<?php

namespace App\Services\Notifications;

use App\Enums\Workspaces\Role;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use App\Notifications\AppNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * The one way member-facing notifications are sent. Domain code says what
 * happened and to whom; this:
 *
 * - drops missing and duplicate recipients;
 * - loads every recipient's preferences in two queries before channels are
 *   chosen (`PreferenceResolver::warm()`);
 * - hands the notification to Laravel, which queues one job per recipient
 *   and channel;
 * - routes it once to the workspace's chat and webhook channels
 *   (`IntegrationRouter`), however many members were notified.
 *
 * Operator alerts go through `AdminAlerts`, never through here.
 */
class NotificationDispatcher
{
    public function __construct(
        private readonly PreferenceResolver $preferences,
        private readonly IntegrationRouter $integrations,
    ) {}

    /**
     * @param  iterable<int, object|null>  $recipients
     */
    public function dispatch(iterable $recipients, Notification $notification): void
    {
        $recipients = $this->uniqueRecipients($recipients);

        if (! $notification instanceof AppNotification) {
            if ($recipients->isNotEmpty()) {
                NotificationFacade::send($recipients, $notification);
            }

            return;
        }

        $this->preferences->warm($recipients->whereInstanceOf(User::class)->values(), $notification->workspaceId);

        if ($recipients->isNotEmpty()) {
            NotificationFacade::send($recipients, $notification);
        }

        $this->integrations->route($notification);
    }

    /**
     * Sends a notification about the person themselves (their assistant,
     * their account): no workspace audience to resolve.
     */
    public function dispatchTo(User $user, Notification $notification): void
    {
        $this->dispatch([$user], $notification);
    }

    /**
     * @return Collection<int, User>
     */
    public function ownersAndAdmins(Workspace $workspace, ?User $except = null): Collection
    {
        return $workspace->users()
            ->whereNull((new WorkspaceMember)->qualifyColumn('deleted_at'))
            ->wherePivotIn('role', [Role::Owner->value, Role::Admin->value])
            ->get()
            ->when($except !== null, fn (Collection $users) => $users->reject(
                fn (User $user): bool => $user->is($except),
            ))
            ->values();
    }

    /**
     * A workspace's owners and admins plus the people directly involved
     * (whoever started a run, whoever set a trigger up), once each. People
     * who are no longer members are left out.
     *
     * @return Collection<int, User>
     */
    public function ownersAdminsAnd(Workspace $workspace, ?User ...$involved): Collection
    {
        $involved = array_values(array_filter($involved));
        $memberIds = $involved === []
            ? collect()
            : $workspace->members()->whereIn('user_id', array_map(fn (User $user): mixed => $user->getKey(), $involved))->pluck('user_id');

        return $this->ownersAndAdmins($workspace)
            ->concat(array_filter($involved, fn (User $user): bool => $memberIds->contains($user->getKey())))
            ->unique(fn (User $user): string => (string) $user->getKey())
            ->values();
    }

    /**
     * @param  iterable<int, object|null>  $recipients
     * @return BaseCollection<int, object>
     */
    private function uniqueRecipients(iterable $recipients): BaseCollection
    {
        return collect($recipients)
            ->filter()
            ->unique(fn (object $recipient): string => $recipient instanceof Model
                ? $recipient->getMorphClass().':'.$recipient->getKey()
                : 'object:'.spl_object_id($recipient))
            ->values();
    }
}
