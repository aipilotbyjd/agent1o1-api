<?php

namespace App\Observers;

use App\Authorization\WorkspaceContext;
use App\Models\Workspaces\WorkspaceMember;

class WorkspaceMemberObserver
{
    public function saved(WorkspaceMember $workspaceMember): void
    {
        WorkspaceContext::forget($workspaceMember->workspace_id, $workspaceMember->user_id);
    }

    public function deleted(WorkspaceMember $workspaceMember): void
    {
        WorkspaceContext::forget($workspaceMember->workspace_id, $workspaceMember->user_id);
    }

    public function restored(WorkspaceMember $workspaceMember): void
    {
        WorkspaceContext::forget($workspaceMember->workspace_id, $workspaceMember->user_id);
    }
}
