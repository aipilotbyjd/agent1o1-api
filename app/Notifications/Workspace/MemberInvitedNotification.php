<?php

namespace App\Notifications\Workspace;

use App\Notifications\AppNotification;
use App\Enums\Notifications\NotificationType;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceInvitation;

class MemberInvitedNotification extends AppNotification
{
    public function __construct(Workspace $workspace, WorkspaceInvitation $invitation, User $inviter)
    {
        parent::__construct(
            workspace: $workspace,
            type: NotificationType::MemberInvited,
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
