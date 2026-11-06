<?php

namespace App\Services\Ai\Connect;

use App\Contracts\Ai\AiProviderConnector;
use App\Enums\Ai\AiProviderAuthMethod;
use App\Exceptions\AiProviderConnectException;
use App\Models\Ai\AiProviderCredential;
use App\Services\Ai\AiProviderSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * A standard OAuth 2 authorization-code flow with PKCE for a provider whose
 * API accepts the resulting access token as its bearer key: Hugging Face
 * (`inference-api` scope, billed to the member's Hugging Face account) and
 * Sign in with ChatGPT (billed to the member's ChatGPT plan). Everything
 * provider-specific is `config('byok.providers.{provider}.connect')`; the
 * login app's client id and secret can also be set by a super admin
 * (`AiProviderSettings::connectClient()`).
 *
 * The access token is stored as the credential's key and renewed with its
 * refresh token before it expires (`AiProviderTokenRefresher`). A provider
 * that doesn't re-issue a refresh token keeps the one it had.
 */
class OAuthAiProviderConnector implements AiProviderConnector
{
    public function __construct(private readonly string $provider) {}

    public function provider(): string
    {
        return $this->provider;
    }

    public function isEnabled(): bool
    {
        return app(AiProviderSettings::class)->connectEnabled($this->provider) && $this->isConfigured();
    }

    public function isConfigured(): bool
    {
        return filled($this->client()['client_id']);
    }

    public function label(): string
    {
        return (string) $this->config('label', 'Connect with '.$this->providerLabel());
    }

    public function authorizeUrl(string $state, string $codeChallenge, string $redirectUri): string
    {
        return $this->config('authorize_url').'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => (string) $this->client()['client_id'],
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', (array) $this->config('scopes', [])),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public function exchange(string $code, string $codeVerifier, string $redirectUri): ConnectedAiCredential
    {
        $response = $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'code_verifier' => $codeVerifier,
        ]);

        return $this->credentialFrom($response);
    }

    public function refresh(AiProviderCredential $credential): ConnectedAiCredential
    {
        $refreshToken = $credential->refreshToken();

        if ($refreshToken === null) {
            throw AiProviderConnectException::cannotRefresh($this->providerLabel());
        }

        $response = $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        $renewed = $this->credentialFrom($response, refused: fn () => AiProviderConnectException::cannotRefresh($this->providerLabel()));

        return $renewed->refreshToken !== null ? $renewed : new ConnectedAiCredential(
            $renewed->apiKey,
            $renewed->authMethod,
            $refreshToken,
            $renewed->expiresAt,
        );
    }

    public function providerOverrides(): array
    {
        return (array) $this->config('provider_overrides', []);
    }

    /**
     * A public client (no secret) is identified by its client id and PKCE
     * alone; a confidential one also sends its secret.
     *
     * @param  array<string, string>  $params
     */
    private function tokenRequest(array $params): Response
    {
        ['client_id' => $clientId, 'client_secret' => $secret] = $this->client();

        try {
            return Http::acceptJson()->asForm()->timeout(30)->connectTimeout(10)
                ->post((string) $this->config('token_url'), [
                    ...$params,
                    'client_id' => (string) $clientId,
                    ...(filled($secret) ? ['client_secret' => (string) $secret] : []),
                ]);
        } catch (ConnectionException) {
            throw AiProviderConnectException::unreachable($this->providerLabel());
        }
    }

    /**
     * @param  (callable(): AiProviderConnectException)|null  $refused
     */
    private function credentialFrom(Response $response, ?callable $refused = null): ConnectedAiCredential
    {
        $accessToken = $response->json('access_token');

        if ($response->failed() || ! is_string($accessToken) || trim($accessToken) === '') {
            Log::warning('AI provider OAuth token request failed.', [
                'provider' => $this->provider,
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ]);

            // An outage or rate limit is not a refusal: a renewal must not
            // take a working connection out of rotation over it.
            if ($response->serverError() || $response->status() === 429) {
                throw AiProviderConnectException::unreachable($this->providerLabel());
            }

            throw $refused !== null ? $refused() : AiProviderConnectException::refused($this->providerLabel(), $response->status());
        }

        $refreshToken = $response->json('refresh_token');
        $expiresIn = $response->json('expires_in');

        return new ConnectedAiCredential(
            trim($accessToken),
            AiProviderAuthMethod::OAuth,
            is_string($refreshToken) && $refreshToken !== '' ? $refreshToken : null,
            is_numeric($expiresIn) ? now()->addSeconds((int) $expiresIn) : null,
        );
    }

    private function providerLabel(): string
    {
        return (string) config("byok.providers.{$this->provider}.label", Str::headline($this->provider));
    }

    /**
     * The login app: set by a super admin, else from `.env`.
     *
     * @return array{client_id: ?string, client_secret: ?string}
     */
    private function client(): array
    {
        return app(AiProviderSettings::class)->connectClient($this->provider);
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return config("byok.providers.{$this->provider}.connect.{$key}", $default);
    }
}
