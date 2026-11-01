<?php

namespace App\Services\Workspaces;

use App\Enums\Billing\PlanLimit;
use App\Enums\Workspaces\Role;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceInvitation;
use App\Models\Workspaces\WorkspaceMember;
use App\Services\Billing\PlanLimitGate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkspaceInvitationService
{
    public function __construct(private readonly PlanLimitGate $limits) {}

    public function invite(Workspace $workspace, string $email, Role $role, User $invitedBy): WorkspaceInvitation
    {
        $alreadyMember = $workspace->users()->where('email', $email)->exists();

        if ($alreadyMember) {
            throw ValidationException::withMessages([
                'email' => 'This person is already a member of the workspace.',
            ]);
        }

        return $workspace->invitations()->create([
            'email' => $email,
            'role' => $role,
            'token' => Str::random(40),
            'invited_by' => $invitedBy->id,
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function accept(WorkspaceInvitation $invitation, User $user): WorkspaceMember
    {
        if ($invitation->isAccepted()) {
            throw ValidationException::withMessages(['token' => 'This invitation has already been accepted.']);
        }

        if ($invitation->isExpired()) {
            throw ValidationException::withMessages(['token' => 'This invitation has expired.']);
        }

        if ($invitation->email !== $user->email) {
            throw ValidationException::withMessages(['email' => 'This invitation was sent to a different email address.']);
        }

        // `count: 0`, not 1: this invitation is still pending, so it is already
        // counted against the seat cap by `PlanLimitGate::usage()` and accepting
        // it only converts that seat from invitation to member: no net gain.
        // The check still bites when the workspace is *already* over cap, which
        // is what catches invitations sent before a downgrade.
        $this->limits->assertCanCreate($invitation->workspace, PlanLimit::Members, count: 0);

        $existing = $invitation->workspace->members()
            ->withTrashed()
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            $existing->restore();
            $existing->update(['role' => $invitation->role, 'joined_at' => now()]);
            $member = $existing;
        } else {
            $member = $invitation->workspace->members()->create([
                'user_id' => $user->id,
                'role' => $invitation->role,
                'invited_by' => $invitation->invited_by,
                'joined_at' => now(),
            ]);
        }

        $invitation->update(['accepted_at' => now()]);

        return $member;
    }

    public function revoke(WorkspaceInvitation $invitation): void
    {
        $invitation->delete();
    }
}
