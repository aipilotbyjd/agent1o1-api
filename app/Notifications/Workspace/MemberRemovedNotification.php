<?php

namespace App\Notifications\Workspace;

use App\Notifications\AppNotification;
use App\Enums\Notifications\NotificationType;
use App\Models\User;
use App\Models\Workspaces\Workspace;

class MemberRemovedNotification extends AppNotification
{
    public function __construct(Workspace $workspace, User $removedUser)
    {
        parent::__construct(
            workspace: $workspace,
            type: NotificationType::MemberRemoved,
            title: "{$removedUser->name} was removed from {$workspace->name}",
            data: ['user_id' => $removedUser->id],
        );
    }
}
