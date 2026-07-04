<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UserService
{
    public function __construct(private PassportTokenService $tokenService) {}

    /**
     * Update name and/or email.
     */
    public function updateProfile(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh();
    }

    /**
     * Change password and revoke all other sessions.
     */
    public function changePassword(User $user, string $newPassword): void
    {
        $currentToken = $user->token();

        $user->update(['password' => $newPassword]);

        $this->tokenService->revokeOtherTokens($user, $currentToken);
    }

    /**
     * Store a new avatar, deleting the previous one.
     */
    public function uploadAvatar(User $user, UploadedFile $file): User
    {
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $file->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return $user->fresh();
    }

    /**
     * Remove the avatar file and clear the field.
     */
    public function deleteAvatar(User $user): User
    {
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return $user->fresh();
    }

    /**
     * Delete avatar file, revoke all tokens, then delete the account.
     */
    public function deleteAccount(User $user): void
    {
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $this->tokenService->revokeAllTokens($user);

        $user->delete();
    }
}
