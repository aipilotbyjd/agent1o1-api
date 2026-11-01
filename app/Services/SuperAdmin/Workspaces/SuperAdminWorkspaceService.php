<?php

namespace App\Services\SuperAdmin\Workspaces;

use App\Models\SuperAdmin\SuperAdmin;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * What a super admin can do to a workspace: lock it (members are refused and
 * no run starts, see `EnsureWorkspaceScope` and `CreditGate`), restore it,
 * and remove a member. Every change is audited with the stated reason.
 */
class SuperAdminWorkspaceService
{
    public function __construct(private readonly SuperAdminAuditLogger $audit) {}

    public function suspend(SuperAdmin $actor, Workspace $workspace, string $reason): Workspace
    {
        if ($workspace->isSuspended()) {
            return $workspace;
        }

        DB::transaction(function () use ($actor, $workspace, $reason): void {
            $workspace->forceFill(['suspended_at' => now(), 'suspended_reason' => $reason])->save();

            $this->audit->record($actor, 'workspaces.suspended', $workspace, ['suspended' => false], ['suspended' => true], $reason);
        });

        return $workspace;
    }

    public function unsuspend(SuperAdmin $actor, Workspace $workspace, string $reason): Workspace
    {
        if (! $workspace->isSuspended()) {
            return $workspace;
        }

        DB::transaction(function () use ($actor, $workspace, $reason): void {
            $workspace->forceFill(['suspended_at' => null, 'suspended_reason' => null])->save();

            $this->audit->record($actor, 'workspaces.unsuspended', $workspace, ['suspended' => true], ['suspended' => false], $reason);
        });

        return $workspace;
    }

    /**
     * The owner cannot be removed (the workspace would be left without one);
     * transferring ownership is the owner's own action.
     */
    public function removeMember(SuperAdmin $actor, Workspace $workspace, WorkspaceMember $member, string $reason): void
    {
        abort_unless($member->workspace_id === $workspace->id, 404);
        abort_if($member->user_id === $workspace->owner_id, 409, 'The workspace owner cannot be removed.');

        DB::transaction(function () use ($actor, $workspace, $member, $reason): void {
            $this->audit->record(
                $actor,
                'workspaces.member_removed',
                $workspace,
                ['user_id' => $member->user_id, 'role' => $member->role->value],
                null,
                $reason,
            );

            $member->delete();
        });
    }
}
