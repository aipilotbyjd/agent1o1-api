<?php

namespace App\Notifications\Workspace;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

class MemberJoinedNotification extends WorkspaceEventNotification
{
    public function __construct(Workspace $workspace, WorkspaceMember $member)
    {
        parent::__construct(
            workspace: $workspace,
            event: NotificationEvent::MemberJoined,
            title: "{$member->user->name} joined {$workspace->name}",
            data: [
                'member_id' => $member->id,
                'user_id' => $member->user_id,
                'role' => $member->role->value,
            ],
        );
    }
}
