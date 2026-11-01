<?php

namespace App\Http\Controllers\Api\Internal\V1\Auth;

use App\Actions\Referrals\AttributeReferralAction;
use App\Enums\Auth\AuthEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Auth\ChangePasswordRequest;
use App\Http\Requests\Api\Internal\V1\Auth\LoginRequest;
use App\Http\Requests\Api\Internal\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\Internal\V1\Auth\VerifyTwoFactorRequest;
use App\Http\Resources\Api\Internal\V1\Auth\AuthEventResource;
use App\Http\Resources\Api\Internal\V1\Auth\TokenResource;
use App\Http\Resources\Api\Internal\V1\User\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\Auth\AuthEventRecorder;
use App\Services\Auth\AuthService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    private const REFRESH_COOKIE = 'refresh_token';

    private const REFRESH_COOKIE_MINUTES = 60 * 24 * 30;

    public function __construct(
        private readonly AuthService $auth,
        private readonly AuthEventRecorder $events,
        private readonly AttributeReferralAction $attributeReferral,
    ) {}

    public function register(RegisterRequest $request)
    {
        $result = $this->auth->register($request->validated());

        $this->attributeSignupToReferrer($request, $result['user']);

        return $this->respondWithTokens($result['user'], $result['tokens'], 'Registered successfully.', 201);
    }

    public function login(LoginRequest $request)
    {
        $result = $this->auth->login($request->validated('email'), $request->validated('password'));

        if (isset($result['two_factor_challenge'])) {
            return ApiResponse::success(
                ['two_factor_challenge' => $result['two_factor_challenge']],
                'Two-factor authentication code required.',
            );
        }

        return $this->respondWithTokens($result['user'], $result['tokens'], 'Logged in successfully.');
    }

    public function verifyTwoFactor(VerifyTwoFactorRequest $request)
    {
        $result = $this->auth->completeTwoFactorChallenge(
            $request->validated('challenge_token'),
            $request->validated('code'),
        );

        return $this->respondWithTokens($result['user'], $result['tokens'], 'Logged in successfully.');
    }

    public function refresh(Request $request)
    {
        $refreshToken = $request->cookie(self::REFRESH_COOKIE);

        abort_if($refreshToken === null, 401, 'Missing refresh token.');

        $tokens = $this->auth->refresh($refreshToken);

        Cookie::queue($this->makeRefreshCookie($tokens['refresh_token']));
        unset($tokens['refresh_token']);

        return ApiResponse::success(['tokens' => $tokens]);
    }

    public function logout(Request $request)
    {
        $this->auth->logout($request->user());
        Cookie::queue(Cookie::forget(self::REFRESH_COOKIE));

        return ApiResponse::noContent();
    }

    public function logoutAll(Request $request)
    {
        $this->auth->logoutAll($request->user());
        Cookie::queue(Cookie::forget(self::REFRESH_COOKIE));

        return ApiResponse::noContent();
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $this->auth->forgotPassword($request->string('email')->toString());

        return ApiResponse::success(message: 'If that email exists, a reset link has been sent.');
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $this->auth->resetPassword($request->only('token', 'email', 'password', 'password_confirmation'));

        return ApiResponse::success(message: 'Password reset successfully.');
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $this->auth->changePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password'),
            (bool) $request->boolean('revoke_other_sessions'),
        );

        return ApiResponse::noContent();
    }

    /**
     * Followed from an email client, so it answers with a redirect into the SPA
     * rather than JSON: matching the password reset link, which has always
     * pointed at the frontend.
     */
    public function verifyEmail(Request $request, string $id)
    {
        if (! $request->hasValidSignature()) {
            return $this->redirectToFrontend('/verify-email', ['status' => 'expired']);
        }

        $user = User::query()->find($id);

        if ($user === null || ! hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
            return $this->redirectToFrontend('/verify-email', ['status' => 'invalid']);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->redirectToFrontend('/verify-email', ['status' => 'already-verified']);
        }

        $user->markEmailAsVerified();

        $this->events->record(AuthEvent::EmailVerified, $user);

        event(new Verified($user));

        return $this->redirectToFrontend('/verify-email', ['status' => 'verified']);
    }

    /**
     * The counterpart for a staged email change. Signed rather than
     * authenticated: the link is followed from the new mailbox, which is very
     * often not the browser holding the session.
     */
    public function confirmEmailChange(Request $request, string $id)
    {
        if (! $request->hasValidSignature()) {
            return $this->redirectToFrontend('/settings/account', ['email_change' => 'expired']);
        }

        $user = User::query()->find($id);

        if ($user === null) {
            return $this->redirectToFrontend('/settings/account', ['email_change' => 'invalid']);
        }

        try {
            $this->auth->confirmEmailChange($user, (string) $request->route('hash'));
        } catch (ValidationException $e) {
            return $this->redirectToFrontend('/settings/account', ['email_change' => 'invalid']);
        }

        return $this->redirectToFrontend('/settings/account', ['email_change' => 'confirmed']);
    }

    public function resendVerification(Request $request)
    {
        $request->user()->sendEmailVerificationNotification();

        return ApiResponse::success(message: 'Verification email sent.');
    }

    public function redirectToProvider(string $provider)
    {
        return ApiResponse::success([
            'url' => Socialite::driver($provider)->stateless()->redirect()->getTargetUrl(),
        ]);
    }

    public function handleProviderCallback(string $provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
            $result = $this->auth->handleSocialCallback($provider, $socialUser);
        } catch (\Throwable $e) {
            report($e);

            return $this->redirectToFrontend('/oauth/callback', ['error' => 'Sign-in failed. Please try again.']);
        }

        $code = $this->auth->issueSocialExchangeCode($result['user'], $provider);

        return $this->redirectToFrontend('/oauth/callback', ['code' => $code]);
    }

    public function exchangeSocialCode(Request $request)
    {
        $request->validate(['code' => ['required', 'string']]);

        $result = $this->auth->exchangeSocialCode($request->string('code')->toString());

        return $this->respondWithTokens($result['user'], $result['tokens'], 'Logged in successfully.');
    }

    public function sessions(Request $request)
    {
        return ApiResponse::success(['sessions' => TokenResource::collection($this->auth->sessions($request->user()))]);
    }

    public function revokeSession(Request $request, string $tokenId)
    {
        $this->auth->revokeSession($request->user(), $tokenId);

        return ApiResponse::noContent();
    }

    /**
     * The account's own security log: the same rows that drive the alert
     * emails, so a user can check "was that me?" without contacting support.
     */
    public function events(Request $request)
    {
        $events = $request->user()
            ->authEvents()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(perPage: max(1, min($request->integer('per_page', 25), 100)));

        return ApiResponse::paginated(AuthEventResource::collection($events));
    }

    /**
     * Credits the signup to whoever's `?ref=` link brought it in. The
     * account already exists by now, so a referral failure is reported and
     * swallowed rather than failing the registration.
     */
    private function attributeSignupToReferrer(RegisterRequest $request, User $user): void
    {
        $code = $request->validated('referral_code');

        if ($code === null) {
            return;
        }

        try {
            $this->attributeReferral->execute($user->fresh(), $code, $request->validated('referral_visitor_id'), $request->ip());
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function respondWithTokens(User $user, array $tokens, string $message, int $status = 200)
    {
        Cookie::queue($this->makeRefreshCookie($tokens['refresh_token']));
        unset($tokens['refresh_token']);

        return ApiResponse::success([
            'user' => UserResource::make($user),
            'tokens' => $tokens,
        ], $message, $status);
    }

    /**
     * @param  array<string, string>  $query
     */
    private function redirectToFrontend(string $path, array $query)
    {
        return redirect(rtrim((string) config('app.frontend_url'), '/').$path.'?'.http_build_query($query));
    }

    private function makeRefreshCookie(string $refreshToken)
    {
        return Cookie::make(
            self::REFRESH_COOKIE,
            $refreshToken,
            self::REFRESH_COOKIE_MINUTES,
            '/',
            null,
            true,
            true,
            false,
            'none',
        );
    }
}
