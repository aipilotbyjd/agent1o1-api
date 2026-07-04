<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\ChangePasswordRequest;
use App\Http\Requests\Api\V1\User\SwitchWorkspaceRequest;
use App\Http\Requests\Api\V1\User\UpdateProfileRequest;
use App\Http\Requests\Api\V1\User\UploadAvatarRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\Workspace;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            'User retrieved.',
            new UserResource($request->user()->load('currentWorkspace')),
        );
    }

    public function switchWorkspace(SwitchWorkspaceRequest $request): JsonResponse
    {
        $user = $request->user();
        $workspace = Workspace::find($request->validated('workspace_id'));

        if (! $workspace) {
            return $this->errorResponse('Workspace not found.', 404);
        }

        $isMember = $workspace->owner_id === $user->id
            || $workspace->members()->where('users.id', $user->id)->exists();

        if (! $isMember) {
            return $this->errorResponse('You are not a member of this workspace.', 403);
        }

        $user->update(['current_workspace_id' => $workspace->id]);

        return $this->successResponse(
            'Active workspace switched.',
            new UserResource($user->load('currentWorkspace')),
        );
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->userService->updateProfile($request->user(), $request->validated());

        return $this->successResponse('Profile updated.', new UserResource($user));
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->userService->changePassword($request->user(), $request->validated('password'));

        return $this->successResponse('Password changed successfully.');
    }

    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = $this->userService->uploadAvatar($request->user(), $request->file('avatar'));

        return $this->successResponse('Avatar uploaded successfully.', new UserResource($user));
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $this->userService->deleteAvatar($request->user());

        return $this->successResponse('Avatar deleted successfully.', new UserResource($user));
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->userService->deleteAccount($request->user());

        return $this->successResponse('Account deleted successfully.');
    }

    public function dismissOnboarding(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->onboarding_dismissed_at === null) {
            $user->update(['onboarding_dismissed_at' => now()]);
        }

        return $this->successResponse('Onboarding dismissed.', new UserResource($user->load('currentWorkspace')));
    }
}
