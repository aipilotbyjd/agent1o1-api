<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\RefreshToken;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class PassportTokenService
{
    public function __construct(private Kernel $httpKernel) {}

    /**
     * Issue access and refresh tokens via the password grant.
     *
     * @return array{token_type: string, expires_in: int, access_token: string, refresh_token: string}
     */
    public function issueTokens(string $email, string $password): array
    {
        $tokens = $this->requestTokens([
            'grant_type' => 'password',
            'username' => $email,
            'password' => $password,
            'scope' => '',
        ]);

        if ($tokens === null) {
            throw new RuntimeException('Unable to issue authentication tokens.');
        }

        return $tokens;
    }

    /**
     * Exchange a refresh token for a new access and refresh token pair.
     *
     * @return array{token_type: string, expires_in: int, access_token: string, refresh_token: string}
     */
    public function refreshTokens(string $refreshToken): array
    {
        $tokens = $this->requestTokens([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'scope' => '',
        ]);

        if ($tokens === null) {
            throw new AuthenticationException('Invalid or expired refresh token.');
        }

        return $tokens;
    }

    /**
     * Revoke an access token and its associated refresh tokens.
     */
    public function revokeToken(AccessToken $token): void
    {
        $token->revoke();

        RefreshToken::query()
            ->where('access_token_id', $token->id)
            ->update(['revoked' => true]);
    }

    /**
     * Revoke all tokens for a user (e.g. after a password reset).
     */
    public function revokeAllTokens(User $user): void
    {
        $tokenIds = $user->tokens()->pluck('id');

        $user->tokens()->update(['revoked' => true]);

        RefreshToken::query()
            ->whereIn('access_token_id', $tokenIds)
            ->update(['revoked' => true]);
    }

    /**
     * Revoke all tokens except the current one (e.g. on password change).
     */
    public function revokeOtherTokens(User $user, AccessToken $currentToken): void
    {
        $otherTokenIds = $user->tokens()
            ->where('id', '!=', $currentToken->id)
            ->pluck('id');

        if ($otherTokenIds->isEmpty()) {
            return;
        }

        $user->tokens()
            ->whereIn('id', $otherTokenIds)
            ->update(['revoked' => true]);

        RefreshToken::query()
            ->whereIn('access_token_id', $otherTokenIds)
            ->update(['revoked' => true]);
    }

    /**
     * Dispatch an internal request to Passport's token endpoint.
     *
     * @param  array<string, string>  $parameters
     * @return array{token_type: string, expires_in: int, access_token: string, refresh_token: string}|null
     */
    private function requestTokens(array $parameters): ?array
    {
        $tokenRequest = Request::create('/oauth/token', 'POST', [
            ...$parameters,
            'client_id' => config('passport.password_client.id'),
            'client_secret' => config('passport.password_client.secret'),
        ]);

        // The kernel rebinds the container's request instance while handling
        // the sub-request, so the original must be restored afterwards.
        $originalRequest = app('request');

        try {
            $response = $this->httpKernel->handle($tokenRequest);
        } finally {
            app()->instance('request', $originalRequest);
        }

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            return null;
        }

        return $this->filterTokenResponse(json_decode($response->getContent(), true));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{token_type: string, expires_in: int, access_token: string, refresh_token: string}
     */
    private function filterTokenResponse(array $data): array
    {
        return [
            'token_type' => $data['token_type'],
            'expires_in' => $data['expires_in'],
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
        ];
    }
}
