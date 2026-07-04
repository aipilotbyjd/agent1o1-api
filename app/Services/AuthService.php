<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private PassportTokenService $tokenService) {}

    /**
     * Register a new user, dispatch the Registered event, and issue tokens.
     *
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{user: User, token: array{token_type: string, expires_in: int, access_token: string, refresh_token: string}}
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        event(new Registered($user));

        return [
            'user' => $user,
            'token' => $this->tokenService->issueTokens($data['email'], $data['password']),
        ];
    }

    /**
     * Validate credentials and issue tokens.
     *
     * @return array{user: User, token: array{token_type: string, expires_in: int, access_token: string, refresh_token: string}}
     */
    public function login(string $email, string $password): array
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        return [
            'user' => $user,
            'token' => $this->tokenService->issueTokens($email, $password),
        ];
    }

    /**
     * Revoke the current access token (logout).
     */
    public function logout(User $user): void
    {
        $this->tokenService->revokeToken($user->token());
    }

    /**
     * Exchange a refresh token for a new token pair.
     *
     * @return array{token_type: string, expires_in: int, access_token: string, refresh_token: string}
     */
    public function refresh(string $refreshToken): array
    {
        return $this->tokenService->refreshTokens($refreshToken);
    }

    /**
     * Send a password reset link (always succeeds to prevent email enumeration).
     */
    public function forgotPassword(string $email): void
    {
        Password::sendResetLink(['email' => $email]);
    }

    /**
     * Reset the password using a valid token and revoke all existing tokens.
     *
     * @param  array{token: string, email: string, password: string}  $credentials
     */
    public function resetPassword(array $credentials): void
    {
        $status = Password::reset(
            $credentials,
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();

                event(new PasswordReset($user));

                $this->tokenService->revokeAllTokens($user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }
    }
}
