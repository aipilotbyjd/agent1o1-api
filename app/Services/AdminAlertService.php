<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Workspace;

class AdminAlertService
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Alert a workspace's owner and admins via in-app notification.
     *
     * @param  array<string, mixed>  $data
     */
    public function alertWorkspaceAdmins(Workspace $workspace, string $title, ?string $body = null, array $data = []): void
    {
        $recipients = $workspace->members()
            ->wherePivotIn('role', [Role::Owner->value, Role::Admin->value])
            ->get();

        // Always include the owner even if not present in the pivot.
        if ($workspace->owner && ! $recipients->contains('id', $workspace->owner_id)) {
            $recipients->push($workspace->owner);
        }

        foreach ($recipients as $user) {
            $this->notifications->notify($user, 'admin_alert', $title, $body, $data, $workspace);
        }
    }
}
