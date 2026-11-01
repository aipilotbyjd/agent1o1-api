<?php

namespace App\Http\Controllers\Api\Internal\V1\Workspaces;

use App\Enums\Billing\PlanLimit;
use App\Enums\Workspaces\Permission;
use App\Enums\Workspaces\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workspaces\InviteMemberRequest;
use App\Http\Resources\Api\Internal\V1\Workspaces\WorkspaceInvitationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceInvitation;
use App\Notifications\Workspace\MemberInvitedNotification;
use App\Notifications\Workspace\MemberJoinedNotification;
use App\Services\Billing\PlanLimitGate;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Workspaces\WorkspaceInvitationService;
use Illuminate\Http\Request;

class WorkspaceInvitationController extends Controller
{
    public function __construct(
        private readonly WorkspaceInvitationService $invitations,
        private readonly NotificationDispatcher $notifications,
        private readonly PlanLimitGate $limits,
    ) {}

    public function index(Workspace $workspace)
    {
        $this->requirePermission(Permission::InvitationView);

        return ApiResponse::success(['invitations' => WorkspaceInvitationResource::collection($workspace->invitations()->whereNull('accepted_at')->get())]);
    }

    public function store(InviteMemberRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::MemberInvite);

        // Fails fast so the inviter learns they're out of seats here, rather
        // than the invitee discovering it when they try to accept. The accept
        // path re-checks: this one can go stale between send and acceptance.
        $this->limits->assertCanCreate($workspace, PlanLimit::Members);

        $invitation = $this->invitations->invite(
            $workspace,
            $request->validated('email'),
            Role::from($request->validated('role')),
            $request->user(),
        );

        $this->notifications->dispatch(
            $this->notifications->ownersAndAdmins($workspace, $request->user()),
            new MemberInvitedNotification($workspace, $invitation, $request->user()),
        );

        return ApiResponse::created(['invitation' => WorkspaceInvitationResource::make($invitation)], 'Invitation sent successfully.');
    }

    public function destroy(Workspace $workspace, WorkspaceInvitation $invitation)
    {
        $this->requirePermission(Permission::MemberInvite);
        $this->ensureBelongsToWorkspace($workspace, $invitation);

        $this->invitations->revoke($invitation);

        return ApiResponse::noContent();
    }

    public function accept(Request $request, WorkspaceInvitation $invitation)
    {
        $member = $this->invitations->accept($invitation, $request->user());
        $member->load('user');

        $this->notifications->dispatch(
            $this->notifications->ownersAndAdmins($invitation->workspace, $member->user),
            new MemberJoinedNotification($invitation->workspace, $member),
        );

        return ApiResponse::success(message: 'Invitation accepted.');
    }
}
