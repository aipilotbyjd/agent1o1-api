<?php

namespace App\Services\Auth;

use App\Enums\Auth\AuthEvent;
use App\Models\Credentials\OAuthConnection;
use App\Models\User;
use App\Notifications\Auth\ConfirmEmailChangeNotification;
use App\Notifications\Auth\SecurityAlertNotification;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Passport;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class AuthService
{
    /**
     * Wrong codes a single two-factor challenge will answer before it is burned
     * and the user has to sign in again. Six digits are only 10^6 wide, so the
     * route's rate limit alone is not a tight enough cap.
     */
    private const MAX_TWO_FACTOR_ATTEMPTS = 5;

    private const TWO_FACTOR_CHALLENGE_MINUTES = 5;

    public function __construct(
        private readonly TwoFactorAuthService $twoFactor,
        private readonly AuthEventRecorder $events,
        private readonly LoginThrottle $throttle,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{user: User, tokens: array}
     */
    public function register(array $data): array
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $user->sendEmailVerificationNotification();

        $this->events->record(AuthEvent::Registered, $user);

        return [
            'user' => $user,
            'tokens' => $this->issuePasswordGrantToken($data['email'], $data['password']),
        ];
    }

    /**
     * @return array{user: User, tokens: array}|array{two_factor_challenge: string}
     */
    public function login(string $email, string $password): array
    {
        $this->throttle->assertNotLocked($email);

        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            $this->recordFailedLogin($email, $user);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $this->assertNotSuspended($user);

        if ($user->hasTwoFactorEnabled()) {
            return ['two_factor_challenge' => $this->createTwoFactorChallenge($user, $password)];
        }

        $this->throttle->clear($email);

        $tokens = $this->issuePasswordGrantToken($email, $password);

        $this->events->recordLogin($user);

        return ['user' => $user, 'tokens' => $tokens];
    }

    /**
     * Completes a 2FA login challenge and issues tokens via the password grant: same
     * shape as a normal login, for frontend parity.
     *
     * The password is held encrypted in the cache alongside the challenge token (5 min TTL,
     * single-use, deleted immediately below) specifically so this step can complete a real
     * password-grant exchange instead of falling back to a refresh-token-less personal
     * access token.
     *
     * @return array{user: User, tokens: array}
     */
    public function completeTwoFactorChallenge(string $challengeToken, string $code): array
    {
        $challenge = Cache::get($this->challengeKey($challengeToken));

        if ($challenge === null) {
            throw ValidationException::withMessages([
                'challenge_token' => 'This two-factor challenge has expired.',
            ]);
        }

        $user = User::query()->findOrFail($challenge['user_id']);

        $this->assertNotSuspended($user);

        if (! $this->twoFactor->verifyCode($user, $code)) {
            $this->recordFailedTwoFactorAttempt($challengeToken, $challenge, $user);

            throw ValidationException::withMessages([
                'code' => 'The provided two-factor code is invalid.',
            ]);
        }

        Cache::forget($this->challengeKey($challengeToken));

        $password = Crypt::decryptString($challenge['password']);

        $this->throttle->clear($user->email);

        $tokens = $this->issuePasswordGrantToken($user->email, $password);

        $this->events->recordLogin($user);

        return ['user' => $user, 'tokens' => $tokens];
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, token_type: string}
     */
    public function refresh(string $refreshToken): array
    {
        $tokens = $this->dispatchTokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => config('passport.password_client_id'),
            'client_secret' => config('passport.password_client_secret'),
            'scope' => '',
        ], 'This refresh token is invalid or has expired.');

        $this->stampDeviceOnToken($tokens['access_token']);

        return $tokens;
    }

    public function logout(User $user): void
    {
        $token = $user->token();

        if ($token) {
            RefreshToken::query()->where('access_token_id', $token->id)->update(['revoked' => true]);
            $token->revoke();
        }

        $this->events->record(AuthEvent::LoggedOut, $user);
    }

    public function logoutAll(User $user): void
    {
        $this->revokeAllTokens($user);

        $this->events->record(AuthEvent::LoggedOutAll, $user);
    }

    public function forgotPassword(string $email): void
    {
        Password::sendResetLink(['email' => $email]);

        $this->events->record(
            AuthEvent::PasswordResetRequested,
            User::query()->where('email', $email)->first(),
            $email,
        );
    }

    /**
     * @param  array{email: string, password: string, token: string}  $data
     */
    public function resetPassword(array $data): void
    {
        $resetUser = null;

        $status = Password::reset($data, function (User $user, string $password) use (&$resetUser) {
            $user->forceFill(['password' => $password])->save();

            $this->revokeAllTokens($user);

            $resetUser = $user;
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        $this->throttle->clear($data['email']);

        $this->events->record(AuthEvent::PasswordReset, $resetUser, $data['email']);
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword, bool $revokeOtherTokens = false): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $user->forceFill(['password' => $newPassword])->save();

        if ($revokeOtherTokens) {
            $currentTokenId = $user->token()?->id;

            $otherTokenIds = $user->tokens()
                ->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))
                ->pluck('id');

            RefreshToken::query()->whereIn('access_token_id', $otherTokenIds)->update(['revoked' => true]);
            $user->tokens()->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))->update(['revoked' => true]);
        }

        $this->events->record(AuthEvent::PasswordChanged, $user, context: [
            'revoked_other_sessions' => $revokeOtherTokens,
        ]);
    }

    /**
     * Stages an email change instead of applying it: the address only moves once
     * the new mailbox proves it can receive mail, and the old address is alerted
     * the moment the request is made (see `AuthEvent::EmailChangeRequested`),
     * because an attacker holding a stolen session would otherwise redirect
     * password resets silently.
     */
    public function requestEmailChange(User $user, string $newEmail): void
    {
        $user->forceFill([
            'pending_email' => $newEmail,
            'pending_email_requested_at' => now(),
        ])->save();

        Notification::route('mail', $newEmail)->notify(
            new ConfirmEmailChangeNotification($this->emailChangeUrl($user, $newEmail), $user->email),
        );

        $this->events->record(AuthEvent::EmailChangeRequested, $user, context: ['pending_email' => $newEmail]);
    }

    public function cancelEmailChange(User $user): void
    {
        $user->forceFill([
            'pending_email' => null,
            'pending_email_requested_at' => null,
        ])->save();
    }

    /**
     * Applies a staged email change. Every session is revoked: the address a
     * password reset goes to has changed, so anything already signed in has to
     * prove itself again.
     */
    public function confirmEmailChange(User $user, string $hash): void
    {
        if ($user->pending_email === null) {
            throw ValidationException::withMessages([
                'email' => 'There is no pending email change for this account.',
            ]);
        }

        if (! hash_equals(sha1($user->pending_email), $hash)) {
            throw ValidationException::withMessages([
                'email' => 'This confirmation link no longer matches the requested address.',
            ]);
        }

        $alreadyTaken = User::query()
            ->where('email', $user->pending_email)
            ->whereKeyNot($user->getKey())
            ->exists();

        if ($alreadyTaken) {
            throw ValidationException::withMessages([
                'email' => 'That email address has since been taken by another account.',
            ]);
        }

        $previousEmail = $user->email;
        $newEmail = $user->pending_email;

        $user->forceFill([
            'email' => $newEmail,
            'email_verified_at' => now(),
            'pending_email' => null,
            'pending_email_requested_at' => null,
        ])->save();

        $this->revokeAllTokens($user);

        Notification::route('mail', $previousEmail)->notify(
            new SecurityAlertNotification(AuthEvent::EmailChanged, request()->ip(), request()->userAgent()),
        );

        $this->events->record(AuthEvent::EmailChanged, $user, context: [
            'previous_email' => $previousEmail,
            'new_email' => $newEmail,
        ]);
    }

    /**
     * Revokes every token before deleting the row. The access tokens alone are
     * not enough: a live refresh token would still mint new ones.
     */
    public function deleteAccount(User $user): void
    {
        $this->revokeAllTokens($user);

        $this->events->record(AuthEvent::AccountDeleted, $user);

        $user->delete();
    }

    /**
     * Resolves (or creates) the account behind a completed provider handshake.
     * Issues no tokens: the caller mints a one-time code and the
     * `social_exchange` grant turns that into a token pair.
     *
     * @return array{user: User, provider: string}
     */
    public function handleSocialCallback(string $provider, SocialiteUser $socialUser): array
    {
        $connection = OAuthConnection::query()
            ->where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        $isNewConnection = $connection === null;

        if ($connection) {
            $user = $connection->user;
        } else {
            $user = User::query()->where('email', $socialUser->getEmail())->first();

            if (! $user) {
                $user = User::query()->create([
                    'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? $socialUser->getEmail(),
                    'email' => $socialUser->getEmail(),
                    'password' => Str::password(32),
                ]);

                // Not passed to create(): `email_verified_at` is deliberately not
                // mass-assignable, so it has to be set explicitly. The provider has
                // already proven the address, so there is nothing to verify.
                $user->markEmailAsVerified();

                $this->events->record(AuthEvent::Registered, $user, context: ['provider' => $provider]);
            }

            OAuthConnection::query()->create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'avatar' => $socialUser->getAvatar(),
            ]);
        }

        if ($isNewConnection) {
            $this->events->record(AuthEvent::SocialAccountLinked, $user, context: ['provider' => $provider]);
        }

        return ['user' => $user, 'provider' => $provider];
    }

    /**
     * Mints the one-time code the social callback hands to the frontend. Only the
     * user id is stored: tokens are issued at exchange time by the
     * `social_exchange` grant, so a leaked code that is never redeemed never
     * corresponded to a token at all.
     */
    public function issueSocialExchangeCode(User $user, string $provider): string
    {
        $code = Str::random(64);

        Cache::put(
            self::socialExchangeKey($code),
            ['user_id' => $user->getKey(), 'provider' => $provider],
            now()->addSeconds(60),
        );

        return $code;
    }

    /**
     * Exchanges that one-time code for a real token pair.
     *
     * This runs the `social_exchange` grant rather than the password grant, so a
     * social sign-in never touches the user's password. The code is consumed by
     * the grant itself (`Cache::pull`), which makes a replay a plain grant
     * failure rather than something this layer has to police.
     *
     * @return array{user: User, tokens: array}
     */
    public function exchangeSocialCode(string $code): array
    {
        $payload = Cache::get(self::socialExchangeKey($code));

        if ($payload === null) {
            throw ValidationException::withMessages([
                'code' => 'This sign-in link has expired. Please try again.',
            ]);
        }

        $user = User::query()->findOrFail($payload['user_id']);

        $this->assertNotSuspended($user);

        $tokens = $this->dispatchTokenRequest([
            'grant_type' => 'social_exchange',
            'client_id' => config('passport.password_client_id'),
            'client_secret' => config('passport.password_client_secret'),
            'code' => $code,
            'scope' => '',
        ], 'This sign-in link has expired. Please try again.');

        $this->stampDeviceOnToken($tokens['access_token']);

        $this->events->recordLogin($user);

        return ['user' => $user, 'tokens' => $tokens];
    }

    public static function socialExchangeKey(string $code): string
    {
        return "oauth-exchange:{$code}";
    }

    /**
     * @return Collection<int, Token>
     */
    public function sessions(User $user)
    {
        return $user->tokens()
            ->where('revoked', false)
            ->with('client')
            ->orderByDesc('created_at')
            ->get();
    }

    public function revokeSession(User $user, string $tokenId): void
    {
        $token = $user->tokens()->where('id', $tokenId)->firstOrFail();

        RefreshToken::query()->where('access_token_id', $token->id)->update(['revoked' => true]);
        $token->revoke();

        $this->events->record(AuthEvent::SessionRevoked, $user, context: ['token_id' => $tokenId]);
    }

    /**
     * A suspended account (see `SuperAdminUserService`) cannot start a session;
     * its existing ones were revoked when it was suspended.
     */
    private function assertNotSuspended(User $user): void
    {
        if ($user->isSuspended()) {
            throw ValidationException::withMessages([
                'email' => 'This account has been suspended. Contact support.',
            ]);
        }
    }

    private function revokeAllTokens(User $user): void
    {
        $tokenIds = $user->tokens()->pluck('id');

        RefreshToken::query()->whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
        $user->tokens()->update(['revoked' => true]);
    }

    private function recordFailedLogin(string $email, ?User $user): void
    {
        $lockedForSeconds = $this->throttle->recordFailure($email);

        $this->events->record(AuthEvent::LoginFailed, $user, $email);

        if ($lockedForSeconds !== null) {
            $this->events->record(AuthEvent::AccountLocked, $user, $email, [
                'locked_for_seconds' => $lockedForSeconds,
            ]);
        }
    }

    /**
     * @param  array{user_id: int, password: string, attempts: int, expires_at: int}  $challenge
     */
    private function recordFailedTwoFactorAttempt(string $challengeToken, array $challenge, User $user): void
    {
        $attempts = $challenge['attempts'] + 1;

        $this->events->record(AuthEvent::TwoFactorChallengeFailed, $user, context: ['attempts' => $attempts]);

        if ($attempts >= self::MAX_TWO_FACTOR_ATTEMPTS) {
            Cache::forget($this->challengeKey($challengeToken));

            throw ValidationException::withMessages([
                'challenge_token' => 'Too many incorrect codes. Sign in again to start a new challenge.',
            ]);
        }

        $challenge['attempts'] = $attempts;

        // Re-put against the original deadline so wrong guesses never extend the window.
        Cache::put($this->challengeKey($challengeToken), $challenge, Carbon::createFromTimestamp($challenge['expires_at']));
    }

    private function createTwoFactorChallenge(User $user, string $password): string
    {
        $challengeToken = Str::random(64);

        Cache::put($this->challengeKey($challengeToken), [
            'user_id' => $user->id,
            'password' => Crypt::encryptString($password),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::TWO_FACTOR_CHALLENGE_MINUTES)->getTimestamp(),
        ], now()->addMinutes(self::TWO_FACTOR_CHALLENGE_MINUTES));

        return $challengeToken;
    }

    private function challengeKey(string $challengeToken): string
    {
        return "2fa-challenge:{$challengeToken}";
    }

    private function emailChangeUrl(User $user, string $newEmail): string
    {
        return URL::temporarySignedRoute('auth.confirm-email-change', now()->addMinutes(60), [
            'id' => $user->getKey(),
            'hash' => sha1($newEmail),
        ]);
    }

    /**
     * Records which device a session belongs to, so the sessions list can show
     * more than a timestamp.
     *
     * The token id is read out of the JWT's own `jti` claim rather than by
     * looking up the user's newest row: the grant may have issued concurrently
     * with another sign-in, and guessing wrong would label the wrong session.
     *
     * The request is resolved here rather than injected because the router
     * caches controller instances on the Route, so a constructor-held request
     * would describe whichever one built the controller first.
     */
    private function stampDeviceOnToken(string $accessToken): void
    {
        $tokenId = $this->tokenIdFromJwt($accessToken);

        if ($tokenId === null) {
            return;
        }

        Passport::token()->newQuery()->whereKey($tokenId)->update([
            'ip_address' => request()->ip(),
            'user_agent' => Str::limit((string) request()->userAgent(), 500, ''),
            'last_used_at' => now(),
        ]);
    }

    private function tokenIdFromJwt(string $jwt): ?string
    {
        $segments = explode('.', $jwt);

        if (count($segments) !== 3) {
            return null;
        }

        $payload = json_decode((string) base64_decode(strtr($segments[1], '-_', '+/'), true), true);

        return is_array($payload) && isset($payload['jti']) ? (string) $payload['jti'] : null;
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, token_type: string}
     */
    private function issuePasswordGrantToken(string $email, string $password): array
    {
        $tokens = $this->dispatchTokenRequest([
            'grant_type' => 'password',
            'client_id' => config('passport.password_client_id'),
            'client_secret' => config('passport.password_client_secret'),
            'username' => $email,
            'password' => $password,
            'scope' => '',
        ], 'These credentials do not match our records.');

        $this->stampDeviceOnToken($tokens['access_token']);

        return $tokens;
    }

    /**
     * Dispatch an in-process request to Passport's own /oauth/token route,
     * avoiding a real network round trip or a live server dependency in tests.
     *
     * @param  array<string, mixed>  $parameters
     * @return array{access_token: string, refresh_token: string, expires_in: int, token_type: string}
     */
    private function dispatchTokenRequest(array $parameters, string $failureMessage): array
    {
        $originalRequest = app('request');

        try {
            $response = app(Kernel::class)->handle(Request::create('/oauth/token', 'POST', $parameters));
        } finally {
            // The kernel rebinds the container's `request` and never restores it,
            // which would leave every later `request()` in this request (resources,
            // the audit trail, device stamping) describing the synthetic sub-request.
            app()->instance('request', $originalRequest);
            Facade::clearResolvedInstance('request');
        }

        $payload = json_decode($response->getContent(), true);

        if ($response->getStatusCode() !== 200) {
            throw ValidationException::withMessages([
                'email' => $payload['error_description'] ?? $failureMessage,
            ]);
        }

        return $payload;
    }
}
