<?php

namespace App\Notifications\Workspace;

use App\Enums\Notifications\NotificationEvent;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceInvitation;

class MemberInvitedNotification extends WorkspaceEventNotification
{
    public function __construct(Workspace $workspace, WorkspaceInvitation $invitation, User $inviter)
    {
        parent::__construct(
            workspace: $workspace,
            event: NotificationEvent::MemberInvited,
            title: "{$inviter->name} invited {$invitation->email} to {$workspace->name}",
            data: [
                'invitation_id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'invited_by' => $inviter->id,
            ],
        );
    }
}
