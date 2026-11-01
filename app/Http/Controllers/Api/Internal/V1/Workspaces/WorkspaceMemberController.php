<?php

namespace App\Http\Controllers\Api\Internal\V1\Workspaces;

use App\Enums\Workspaces\Permission;
use App\Enums\Workspaces\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workspaces\UpdateMemberRoleRequest;
use App\Http\Resources\Api\Internal\V1\Workspaces\WorkspaceMemberResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Notifications\Workspace\MemberRemovedNotification;
use App\Notifications\Workspace\MemberRoleChangedNotification;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Request;

class WorkspaceMemberController extends Controller
{
    public function __construct(
        private readonly WorkspaceService $workspaces,
        private readonly NotificationDispatcher $notifications,
    ) {}

    public function index(Workspace $workspace)
    {
        $this->requirePermission(Permission::MemberView);

        return ApiResponse::success(['members' => WorkspaceMemberResource::collection($workspace->members()->with('user')->get())]);
    }

    public function updateRole(UpdateMemberRoleRequest $request, Workspace $workspace, User $member)
    {
        $this->requirePermission(Permission::MemberUpdateRole);

        $workspaceMember = $workspace->members()->where('user_id', $member->id)->firstOrFail();
        $previousRole = $workspaceMember->role;

        $this->workspaces->updateMemberRole($workspace, $member, Role::from($request->validated('role')));

        $workspaceMember->refresh()->load('user');

        $this->notifications->dispatch(
            $this->notifications->ownersAndAdmins($workspace, $request->user()),
            new MemberRoleChangedNotification($workspace, $workspaceMember, $previousRole),
        );

        return ApiResponse::noContent();
    }

    public function destroy(Request $request, Workspace $workspace, User $member)
    {
        $this->requirePermission(Permission::MemberRemove);

        $this->workspaces->removeMember($workspace, $member);

        $this->notifications->dispatch(
            $this->notifications->ownersAndAdmins($workspace, $request->user()),
            new MemberRemovedNotification($workspace, $member),
        );

        return ApiResponse::noContent();
    }

    public function leave(Request $request, Workspace $workspace)
    {
        $this->workspaces->leave($workspace, $request->user());

        return ApiResponse::noContent();
    }
}
