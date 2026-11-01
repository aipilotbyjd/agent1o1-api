<?php

namespace App\Services\SuperAdmin\Users;

use App\Models\SuperAdmin\SuperAdmin;
use App\Models\User;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\RefreshToken;

/**
 * What a super admin can do to a platform user: suspend (lock out), restore,
 * and end their sessions. Every change is audited with the stated reason.
 *
 * A suspension has two halves: the flag, which `AuthService` checks before it
 * starts any session, and the revocation of every session already open. Both
 * are needed: the flag alone would leave a signed-in user working until their
 * token expired, and revocation alone would let them sign straight back in.
 */
class SuperAdminUserService
{
    public function __construct(private readonly SuperAdminAuditLogger $audit) {}

    public function suspend(SuperAdmin $actor, User $user, string $reason): User
    {
        if ($user->isSuspended()) {
            return $user;
        }

        DB::transaction(function () use ($actor, $user, $reason): void {
            $user->forceFill(['suspended_at' => now(), 'suspended_reason' => $reason])->save();

            $this->audit->record($actor, 'users.suspended', $user, ['suspended' => false], ['suspended' => true], $reason);
        });

        $this->revokeSessions($user);

        return $user;
    }

    public function unsuspend(SuperAdmin $actor, User $user, string $reason): User
    {
        if (! $user->isSuspended()) {
            return $user;
        }

        DB::transaction(function () use ($actor, $user, $reason): void {
            $user->forceFill(['suspended_at' => null, 'suspended_reason' => null])->save();

            $this->audit->record($actor, 'users.unsuspended', $user, ['suspended' => true], ['suspended' => false], $reason);
        });

        return $user;
    }

    public function forceLogout(SuperAdmin $actor, User $user, string $reason): User
    {
        $this->revokeSessions($user);

        $this->audit->record($actor, 'users.force_logout', $user, reason: $reason);

        return $user;
    }

    /**
     * Sends the verification email again. Refused for an address that is
     * already verified, so it can't be used to mail an inbox for no reason.
     */
    public function resendVerification(SuperAdmin $actor, User $user, string $reason): void
    {
        abort_if($user->hasVerifiedEmail(), 409, 'This user has already verified their email.');

        $user->sendEmailVerificationNotification();

        $this->audit->record($actor, 'users.verification_resent', $user, reason: $reason);
    }

    /**
     * Revokes every access token and refresh token: the access tokens alone
     * are not enough, a live refresh token would mint new ones.
     */
    private function revokeSessions(User $user): void
    {
        $tokenIds = $user->tokens()->pluck('id');

        RefreshToken::query()->whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
        $user->tokens()->update(['revoked' => true]);
    }
}
