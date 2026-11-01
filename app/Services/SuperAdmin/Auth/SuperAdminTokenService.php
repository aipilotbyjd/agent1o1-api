<?php

namespace App\Services\SuperAdmin\Auth;

use App\Enums\SuperAdmin\SuperAdminAuthEvent;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\SuperAdmin\SuperAdminToken;
use App\Services\SuperAdmin\Alerts\SuperAdminAlerter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Issues, validates, rotates and revokes Super Admin tokens.
 *
 * Opaque random strings, stored only as SHA-256 hashes. A refresh rotates both
 * tokens inside one `family_id`; presenting a refresh token that was already
 * rotated (or revoked) kills the whole family and raises an alert, since only
 * a copied token can arrive twice.
 */
class SuperAdminTokenService
{
    /**
     * How stale `last_used_at` may get before a request rewrites it.
     */
    private const TOUCH_INTERVAL_MINUTES = 5;

    public function __construct(
        private readonly SuperAdminLoginEventRecorder $events,
        private readonly SuperAdminAlerter $alerter,
    ) {}

    /**
     * @return array{token: SuperAdminToken, access_token: string, refresh_token: ?string, expires_in: int, token_type: string}
     */
    public function issue(
        SuperAdmin $admin,
        string $scope = SuperAdminToken::SCOPE_FULL,
        ?string $familyId = null,
        ?Carbon $absoluteExpiresAt = null,
    ): array {
        $isSetup = $scope === SuperAdminToken::SCOPE_SETUP;

        $accessToken = Str::random(64);
        $refreshToken = $isSetup ? null : Str::random(64);

        $absoluteExpiresAt ??= now()->addDays((int) config('super_admin_auth.session_absolute_days'));
        $accessMinutes = $isSetup
            ? (int) config('super_admin_auth.setup_token_minutes')
            : (int) config('super_admin_auth.access_token_minutes');

        $refreshExpiresAt = $isSetup ? null : now()->addDays((int) config('super_admin_auth.refresh_token_days'));
        if ($refreshExpiresAt !== null && $refreshExpiresAt->gt($absoluteExpiresAt)) {
            $refreshExpiresAt = $absoluteExpiresAt->copy();
        }

        $request = request();

        $token = SuperAdminToken::query()->create([
            'super_admin_id' => $admin->id,
            'family_id' => $familyId ?? (string) Str::uuid(),
            'scope' => $scope,
            'access_token_hash' => $this->hash($accessToken),
            'refresh_token_hash' => $refreshToken === null ? null : $this->hash($refreshToken),
            'access_expires_at' => now()->addMinutes($accessMinutes),
            'refresh_expires_at' => $refreshExpiresAt,
            'absolute_expires_at' => $absoluteExpiresAt,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'last_used_at' => now(),
        ]);

        return [
            'token' => $token,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $accessMinutes * 60,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Resolves the account behind the request's bearer token, or null. This
     * is what the `super-admin` guard calls; the token row is parked on the
     * request so middleware and controllers can read its scope and family.
     */
    public function authenticate(Request $request): ?SuperAdmin
    {
        if (! config('super_admin.enabled')) {
            return null;
        }

        $plain = $request->bearerToken();

        if ($plain === null || $plain === '') {
            return null;
        }

        $token = SuperAdminToken::query()
            ->with('admin')
            ->where('access_token_hash', $this->hash($plain))
            ->first();

        if ($token === null || $token->isRevoked() || $token->accessExpired()) {
            return null;
        }

        $admin = $token->admin;

        if ($admin === null || $admin->isDisabled()) {
            return null;
        }

        $this->touch($token, $request);

        $request->attributes->set('super_admin_token', $token);

        return $admin;
    }

    /**
     * Exchanges a refresh token for a fresh pair in the same session family.
     *
     * @return array{admin: SuperAdmin, tokens: array{token: SuperAdminToken, access_token: string, refresh_token: ?string, expires_in: int, token_type: string}}
     */
    public function refresh(string $plainRefreshToken): array
    {
        $token = SuperAdminToken::query()
            ->with('admin')
            ->where('refresh_token_hash', $this->hash($plainRefreshToken))
            ->first();

        if ($token === null) {
            throw $this->invalidRefreshToken();
        }

        if ($token->isRevoked()) {
            $this->revokeFamily($token->family_id);

            $this->events->record(SuperAdminAuthEvent::RefreshReuseDetected, $token->admin, context: ['family_id' => $token->family_id]);
            $this->alerter->raise(
                'refresh_reuse_detected',
                'A Super Admin refresh token was used twice; its session was revoked.',
                ['super_admin_id' => $token->super_admin_id, 'ip' => request()->ip()],
            );

            throw $this->invalidRefreshToken();
        }

        $admin = $token->admin;

        if (! $token->isFull() || $token->refreshExpired() || $admin === null || $admin->isDisabled()) {
            throw $this->invalidRefreshToken();
        }

        $token->forceFill(['revoked_at' => now()])->save();

        return [
            'admin' => $admin,
            'tokens' => $this->issue($admin, SuperAdminToken::SCOPE_FULL, $token->family_id, $token->absolute_expires_at),
        ];
    }

    public function revokeFamily(string $familyId): void
    {
        SuperAdminToken::query()
            ->where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /**
     * Revokes every live session of an account, optionally sparing one family
     * (the caller's own, when it changes its password).
     */
    public function revokeAllFor(SuperAdmin $admin, ?string $exceptFamilyId = null): void
    {
        SuperAdminToken::query()
            ->where('super_admin_id', $admin->id)
            ->whereNull('revoked_at')
            ->when($exceptFamilyId !== null, fn ($query) => $query->where('family_id', '!=', $exceptFamilyId))
            ->update(['revoked_at' => now()]);
    }

    /**
     * Ends every live session of every super admin. For an emergency (see
     * `super-admin:lockdown`); returns how many sessions it ended.
     */
    public function revokeEverything(): int
    {
        return SuperAdminToken::query()->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    /**
     * Strips the ability to refresh from every session, so the admin has to
     * sign in again within one access-token lifetime (used on demotion).
     */
    public function revokeRefreshTokensFor(SuperAdmin $admin): void
    {
        SuperAdminToken::query()
            ->where('super_admin_id', $admin->id)
            ->whereNull('revoked_at')
            ->whereNotNull('refresh_token_hash')
            ->update(['refresh_expires_at' => now()]);
    }

    private function touch(SuperAdminToken $token, Request $request): void
    {
        if ($token->last_used_at !== null && $token->last_used_at->gt(now()->subMinutes(self::TOUCH_INTERVAL_MINUTES))) {
            return;
        }

        $token->forceFill([
            'last_used_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ])->save();
    }

    private function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    private function invalidRefreshToken(): HttpException
    {
        return new HttpException(401, 'This refresh token is invalid or has expired.');
    }
}
