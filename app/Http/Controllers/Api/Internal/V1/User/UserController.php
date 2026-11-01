<?php

namespace App\Http\Controllers\Api\Internal\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\User\SwitchWorkspaceRequest;
use App\Http\Requests\Api\Internal\V1\User\UpdateUserRequest;
use App\Http\Requests\Api\Internal\V1\User\UploadAvatarRequest;
use App\Http\Resources\Api\Internal\V1\User\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Services\Auth\AuthService;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function __construct(
        private readonly WorkspaceService $workspaces,
        private readonly AuthService $auth,
    ) {}

    public function show(Request $request)
    {
        return ApiResponse::success(['user' => UserResource::make($request->user())]);
    }

    public function switchWorkspace(SwitchWorkspaceRequest $request)
    {
        $workspace = Workspace::findOrFail($request->validated('workspace_id'));

        $this->workspaces->switchTo($request->user(), $workspace);

        return ApiResponse::success(['user' => UserResource::make($request->user()->fresh())]);
    }

    /**
     * A new email is staged rather than applied (see
     * `AuthService::requestEmailChange()`) so the response reports it as
     * pending and the account keeps its current address until the new mailbox
     * confirms.
     */
    public function update(UpdateUserRequest $request)
    {
        $user = $request->user();

        $user->update($request->safe()->only(['name', 'timezone']));

        $newEmail = $request->validated('email');
        $emailChangeRequested = $newEmail !== null && $newEmail !== $user->email;

        if ($emailChangeRequested) {
            $this->auth->requestEmailChange($user, $newEmail);
        }

        return ApiResponse::success(
            ['user' => UserResource::make($user->fresh())],
            $emailChangeRequested
                ? "Profile updated. Confirm the change from the link we sent to {$newEmail}: until then your sign-in address stays the same."
                : 'User updated successfully.',
        );
    }

    public function cancelEmailChange(Request $request)
    {
        $this->auth->cancelEmailChange($request->user());

        return ApiResponse::success(
            ['user' => UserResource::make($request->user()->fresh())],
            'Pending email change cancelled.',
        );
    }

    public function uploadAvatar(UploadAvatarRequest $request)
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');

        $user->update(['avatar' => $path]);

        return ApiResponse::success(['user' => UserResource::make($user)], 'Avatar uploaded successfully.');
    }

    public function deleteAvatar(Request $request)
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return ApiResponse::success(['user' => UserResource::make($user)], 'Avatar removed successfully.');
    }

    public function destroy(Request $request)
    {
        $this->auth->deleteAccount($request->user());

        return ApiResponse::noContent();
    }
}
