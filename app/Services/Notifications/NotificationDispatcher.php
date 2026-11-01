<?php

namespace App\Services\Notifications;

use App\Enums\Workspaces\Role;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class NotificationDispatcher
{
    /**
     * @param  iterable<int, User>  $recipients
     */
    public function dispatch(iterable $recipients, Notification $notification): void
    {
        NotificationFacade::send($recipients, $notification);
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
}
