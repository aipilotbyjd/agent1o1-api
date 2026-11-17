<?php

namespace App\Notifications\Workspace;

use App\Enums\Notifications\NotificationType;
use App\Enums\Workspaces\Role;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use App\Notifications\AppNotification;

class MemberRoleChangedNotification extends AppNotification
{
    public function __construct(Workspace $workspace, WorkspaceMember $member, Role $previousRole)
    {
        parent::__construct(
            workspace: $workspace,
            type: NotificationType::MemberRoleChanged,
            title: "{$member->user->name}'s role changed from {$previousRole->value} to {$member->role->value} in {$workspace->name}",
            data: [
                'member_id' => $member->id,
                'user_id' => $member->user_id,
                'previous_role' => $previousRole->value,
                'role' => $member->role->value,
            ],
        );
    }
}
