<?php

namespace App\Services\SuperAdmin\Auth;

use App\Enums\SuperAdmin\SuperAdminAuthEvent;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\SuperAdmin\SuperAdminToken;
use App\Services\SuperAdmin\Alerts\SuperAdminAlerter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The Super Admin sign-in flow:
 *
 *  1. `login` checks the password. With two-factor confirmed it returns a
 *     short-lived challenge (no tokens yet); without it, a restricted "setup"
 *     token that only opens password change and two-factor setup.
 *  2. `completeTwoFactorChallenge` trades the challenge plus a code for the
 *     real token pair.
 *
 * Every failure on the way in answers with the same message, so the endpoint
 * does not reveal which accounts exist.
 */
class SuperAdminAuthService
{
    public function __construct(
        private readonly SuperAdminTokenService $tokens,
        private readonly SuperAdminTwoFactorService $twoFactor,
        private readonly SuperAdminLoginThrottle $throttle,
        private readonly SuperAdminLoginEventRecorder $events,
        private readonly SuperAdminIpPolicy $ips,
        private readonly SuperAdminAlerter $alerter,
    ) {}

    /**
     * @return array{stage: 'two_factor_challenge', challenge_token: string}|array{stage: 'setup_required', admin: SuperAdmin, tokens: array}
     */
    public function login(string $email, string $password, ?string $ip): array
    {
        $email = Str::lower(trim($email));
        $admin = SuperAdmin::query()->where('email', $email)->first();

        if ($admin !== null) {
            $this->throttle->assertNotLocked($admin);
        }

        // Always spend a hash check, so a missing account costs the same time as a wrong password.
        $passwordMatches = Hash::check($password, $admin?->password ?? $this->dummyHash());

        if ($admin === null || $admin->isDisabled() || ! $passwordMatches) {
            $this->failLogin($admin, $email);
        }

        if (! $this->ips->allowsAccount($admin, $ip)) {
            $this->events->record(SuperAdminAuthEvent::IpBlocked, $admin, context: ['stage' => 'login']);
            $this->alerter->raise('ip_blocked', 'A Super Admin sign-in was blocked by the IP allowlist.', [
                'super_admin_id' => $admin->id,
                'ip' => $ip,
            ]);

            throw new HttpException(403, 'This account cannot sign in from this location.');
        }

        $this->throttle->clear($admin);

        if ($admin->hasTwoFactorEnabled()) {
            return ['stage' => 'two_factor_challenge', 'challenge_token' => $this->createChallenge($admin)];
        }

        $this->events->record(SuperAdminAuthEvent::LoginSucceeded, $admin, context: ['stage' => 'setup']);

        return [
            'stage' => 'setup_required',
            'admin' => $admin,
            'tokens' => $this->tokens->issue($admin, SuperAdminToken::SCOPE_SETUP),
        ];
    }

    /**
     * @return array{admin: SuperAdmin, tokens: array}
     */
    public function completeTwoFactorChallenge(string $challengeToken, string $code, ?string $ip): array
    {
        $challenge = Cache::get($this->challengeKey($challengeToken));
        $admin = $challenge === null ? null : SuperAdmin::query()->find($challenge['admin_id']);

        if ($challenge === null || $admin === null || $admin->isDisabled()) {
            Cache::forget($this->challengeKey($challengeToken));

            throw ValidationException::withMessages([
                'challenge_token' => 'This two-factor challenge has expired.',
            ]);
        }

        if (! $this->twoFactor->verifyCode($admin, $code)) {
            $this->recordFailedTwoFactorAttempt($challengeToken, $challenge, $admin);

            throw ValidationException::withMessages([
                'code' => 'The provided two-factor code is invalid.',
            ]);
        }

        Cache::forget($this->challengeKey($challengeToken));

        $previousIp = $admin->last_login_ip;

        $admin->forceFill(['last_login_at' => now(), 'last_login_ip' => $ip])->save();

        $this->events->record(SuperAdminAuthEvent::LoginSucceeded, $admin, context: ['stage' => 'full']);

        if ($previousIp !== null && $previousIp !== $ip) {
            $this->alerter->raise('login_new_ip', 'A super admin signed in from a new address.', [
                'super_admin_id' => $admin->id,
                'email' => $admin->email,
                'previous_ip' => $previousIp,
                'ip' => $ip,
            ]);
        }

        return ['admin' => $admin, 'tokens' => $this->tokens->issue($admin)];
    }

    /**
     * @return array{admin: SuperAdmin, tokens: array}
     */
    public function refresh(string $refreshToken, ?string $ip): array
    {
        $result = $this->tokens->refresh($refreshToken);

        if (! $this->ips->allowsAccount($result['admin'], $ip)) {
            $this->tokens->revokeFamily($result['tokens']['token']->family_id);
            $this->events->record(SuperAdminAuthEvent::IpBlocked, $result['admin'], context: ['stage' => 'refresh']);

            throw new HttpException(403, 'This account cannot sign in from this location.');
        }

        $this->events->record(SuperAdminAuthEvent::TokenRefreshed, $result['admin']);

        return $result;
    }

    public function logout(SuperAdmin $admin, SuperAdminToken $current): void
    {
        $this->tokens->revokeFamily($current->family_id);

        $this->events->record(SuperAdminAuthEvent::LoggedOut, $admin);
    }

    public function logoutAll(SuperAdmin $admin): void
    {
        $this->tokens->revokeAllFor($admin);

        $this->events->record(SuperAdminAuthEvent::LoggedOutAll, $admin);
    }

    public function revokeSession(SuperAdmin $admin, string $tokenId): void
    {
        $token = $admin->tokens()->whereKey($tokenId)->firstOrFail();

        $this->tokens->revokeFamily($token->family_id);

        $this->events->record(SuperAdminAuthEvent::SessionRevoked, $admin, context: ['token_id' => $tokenId]);
    }

    /**
     * Changes the password and signs every other session out. The current
     * session keeps working so the caller is not thrown out of the UI.
     */
    public function changePassword(SuperAdmin $admin, SuperAdminToken $current, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $admin->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $admin->forceFill(['password' => $newPassword, 'password_changed_at' => now()])->save();

        $this->tokens->revokeAllFor($admin, exceptFamilyId: $current->family_id);

        $this->events->record(SuperAdminAuthEvent::PasswordChanged, $admin);
    }

    /**
     * @return array{secret: string, otpauth_url: string}
     */
    public function enableTwoFactor(SuperAdmin $admin): array
    {
        $setup = $this->twoFactor->enable($admin);

        $this->events->record(SuperAdminAuthEvent::TwoFactorEnabled, $admin);

        return $setup;
    }

    /**
     * Confirms two-factor setup and ends every session of the account, the
     * setup token included: the admin must now sign in again with a code, which
     * is the only way to a full token.
     *
     * @return array<int, string> The plain recovery codes, shown once.
     */
    public function confirmTwoFactor(SuperAdmin $admin, string $code): array
    {
        $recoveryCodes = $this->twoFactor->confirm($admin, $code);

        $this->tokens->revokeAllFor($admin);

        $this->events->record(SuperAdminAuthEvent::TwoFactorConfirmed, $admin);

        return $recoveryCodes;
    }

    /**
     * @return array<int, string>
     */
    public function regenerateRecoveryCodes(SuperAdmin $admin): array
    {
        $codes = $this->twoFactor->regenerateRecoveryCodes($admin);

        $this->events->record(SuperAdminAuthEvent::RecoveryCodesRegenerated, $admin);

        return $codes;
    }

    private function failLogin(?SuperAdmin $admin, string $email): never
    {
        $lockedForSeconds = $this->throttle->recordFailure($admin);

        $this->events->record(SuperAdminAuthEvent::LoginFailed, $admin, $email);

        if ($lockedForSeconds !== null) {
            $this->events->record(SuperAdminAuthEvent::LockedOut, $admin, context: ['locked_for_seconds' => $lockedForSeconds]);
            $this->alerter->raise('locked_out', 'A super admin account was locked after repeated failed sign-ins.', [
                'super_admin_id' => $admin?->id,
                'locked_for_seconds' => $lockedForSeconds,
                'ip' => request()->ip(),
            ]);
        }

        throw ValidationException::withMessages([
            'email' => 'These credentials do not match our records.',
        ]);
    }

    /**
     * @param  array{admin_id: string, attempts: int, expires_at: int}  $challenge
     */
    private function recordFailedTwoFactorAttempt(string $challengeToken, array $challenge, SuperAdmin $admin): void
    {
        $attempts = $challenge['attempts'] + 1;

        $this->events->record(SuperAdminAuthEvent::TwoFactorFailed, $admin, context: ['attempts' => $attempts]);

        if ($attempts >= (int) config('super_admin_auth.max_two_factor_attempts')) {
            Cache::forget($this->challengeKey($challengeToken));

            throw ValidationException::withMessages([
                'challenge_token' => 'Too many incorrect codes. Sign in again to start a new challenge.',
            ]);
        }

        $challenge['attempts'] = $attempts;

        // Re-put against the original deadline so wrong guesses never extend the window.
        Cache::put($this->challengeKey($challengeToken), $challenge, now()->setTimestamp($challenge['expires_at']));
    }

    private function createChallenge(SuperAdmin $admin): string
    {
        $challengeToken = Str::random(64);
        $expiresAt = now()->addMinutes((int) config('super_admin_auth.challenge_minutes'));

        Cache::put($this->challengeKey($challengeToken), [
            'admin_id' => $admin->id,
            'attempts' => 0,
            'expires_at' => $expiresAt->getTimestamp(),
        ], $expiresAt);

        return $challengeToken;
    }

    private function challengeKey(string $challengeToken): string
    {
        return 'super-admin-2fa-challenge:'.hash('sha256', $challengeToken);
    }

    private function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make(Str::random(32));
    }
}
