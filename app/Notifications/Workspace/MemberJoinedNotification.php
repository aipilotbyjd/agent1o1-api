<?php

namespace App\Notifications\Workspace;

use App\Enums\Notifications\NotificationType;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use App\Notifications\AppNotification;

class MemberJoinedNotification extends AppNotification
{
    public function __construct(Workspace $workspace, WorkspaceMember $member)
    {
        parent::__construct(
            workspace: $workspace,
            type: NotificationType::MemberJoined,
            title: "{$member->user->name} joined {$workspace->name}",
            data: [
                'member_id' => $member->id,
                'user_id' => $member->user_id,
                'role' => $member->role->value,
            ],
        );
    }
}
