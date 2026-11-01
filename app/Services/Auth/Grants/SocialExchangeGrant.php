<?php

namespace App\Services\Auth\Grants;

use App\Services\Auth\AuthService;
use DateInterval;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Bridge\User as UserEntity;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Exchanges the one-time code minted by a completed social sign-in for a real
 * access/refresh token pair.
 *
 * This grant exists so social sign-in never has to touch the user's password.
 * The obvious shortcut (rotating the password to a random value and running the
 * password grant) is what this replaces: it worked, but it silently destroyed
 * the credential of anyone who also signs in with a password, which permanently
 * ruled out letting a social account set one.
 *
 * The code is consumed with `Cache::pull`, so a replayed code simply fails to
 * resolve a user and the grant rejects it.
 */
class SocialExchangeGrant extends AbstractGrant
{
    public function getIdentifier(): string
    {
        return 'social_exchange';
    }

    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL,
    ): ResponseTypeInterface {
        $client = $this->validateClient($request);
        $scopes = $this->validateScopes($this->getRequestParameter('scope', $request, $this->defaultScope));
        $user = $this->validateUser($request, $client);

        $finalizedScopes = $this->scopeRepository->finalizeScopes(
            $scopes,
            $this->getIdentifier(),
            $client,
            $user->getIdentifier(),
        );

        $accessToken = $this->issueAccessToken($accessTokenTTL, $client, $user->getIdentifier(), $finalizedScopes);
        $responseType->setAccessToken($accessToken);

        $refreshToken = $this->issueRefreshToken($accessToken);

        if ($refreshToken !== null) {
            $responseType->setRefreshToken($refreshToken);
        }

        return $responseType;
    }

    private function validateUser(ServerRequestInterface $request, ClientEntityInterface $client): UserEntityInterface
    {
        $code = $this->getRequestParameter('code', $request);

        if (! is_string($code) || $code === '') {
            throw OAuthServerException::invalidRequest('code');
        }

        $payload = Cache::pull(AuthService::socialExchangeKey($code));

        if (! is_array($payload) || ! isset($payload['user_id'])) {
            throw OAuthServerException::invalidGrant('This sign-in link has expired.');
        }

        return new UserEntity((string) $payload['user_id']);
    }
}
