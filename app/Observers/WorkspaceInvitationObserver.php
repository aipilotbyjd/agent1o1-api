<?php

namespace App\Observers;

use App\Models\Workspaces\WorkspaceInvitation;
use App\Services\Analytics\ProductAnalytics;

/**
 * Tells PostHog when someone invites a teammate. The invited email address is
 * personal data and is never sent: only the role they were invited as.
 */
class WorkspaceInvitationObserver
{
    public function created(WorkspaceInvitation $workspaceInvitation): void
    {
        app(ProductAnalytics::class)->capture(
            $workspaceInvitation->invited_by !== null ? (string) $workspaceInvitation->invited_by : null,
            'team_invite_sent',
            ['role' => $workspaceInvitation->role->value],
            (string) $workspaceInvitation->workspace_id,
        );
    }
}
