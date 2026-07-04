<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workspace\RemoveMemberRequest;
use App\Http\Requests\Api\V1\Workspace\TransferOwnershipRequest;
use App\Http\Requests\Api\V1\Workspace\UpdateMemberRoleRequest;
use App\Http\Resources\V1\WorkspaceMemberResource;
use App\Http\Resources\V1\WorkspaceResource;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceMemberController extends Controller
{
    public function __construct(private WorkspaceService $workspaceService) {}

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::MemberView)) {
            return $forbidden;
        }

        $members = $workspace->members()
            ->paginate(50);

        return $this->paginatedResponse(
            'Members retrieved.',
            WorkspaceMemberResource::collection($members),
        );
    }

    public function update(UpdateMemberRoleRequest $request, Workspace $workspace, User $user): JsonResponse
    {
        $affected = $workspace->members()->updateExistingPivot($user->id, ['role' => $request->validated('role')]);

        if ($affected === 0) {
            return $this->errorResponse('User is not a member of this workspace.', 404);
        }

        return $this->successResponse('Member role updated.');
    }

    public function destroy(RemoveMemberRequest $request, Workspace $workspace, User $user): JsonResponse
    {
        $this->workspaceService->removeMember($workspace, $user, $request->user());

        return $this->successResponse('Member removed.');
    }

    public function leave(Request $request, Workspace $workspace): JsonResponse
    {
        $user = $request->user();

        if ($workspace->owner_id === $user->id) {
            return $this->errorResponse('The workspace owner cannot leave. Transfer ownership first.', 422);
        }

        $this->workspaceService->leave($workspace, $user);

        return $this->successResponse('You have left the workspace.');
    }

    public function transferOwnership(TransferOwnershipRequest $request, Workspace $workspace): JsonResponse
    {
        $newOwner = User::findOrFail($request->validated('user_id'));
        $workspace = $this->workspaceService->transferOwnership($workspace, $newOwner);

        return $this->successResponse(
            'Ownership transferred.',
            new WorkspaceResource($workspace->load('owner')),
        );
    }
}
