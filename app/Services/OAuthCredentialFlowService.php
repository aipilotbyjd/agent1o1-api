<?php

namespace App\Services;

use App\Models\Credential;
use App\Models\CredentialType;
use App\Models\OAuthCredentialState;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class OAuthCredentialFlowService
{
    /**
     * Begin an OAuth2 authorization-code flow.
     *
     * @return array{authorize_url: string, state: string}
     */
    public function initiate(Workspace $workspace, User $user, string $typeKey, string $name): array
    {
        $type = $this->resolveOAuthType($typeKey);
        $client = $this->clientConfig($typeKey);

        $scopes = $type->oauth['scopes'] ?? [];
        $state = Str::random(48);

        OAuthCredentialState::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'credential_type_key' => $typeKey,
            'state' => $state,
            'name' => $name,
            'scopes' => $scopes,
            'redirect_uri' => $this->callbackUri(),
            'expires_at' => now()->addMinutes(15),
        ]);

        $query = http_build_query([
            'client_id' => $client['client_id'],
            'redirect_uri' => $this->callbackUri(),
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'access_type' => 'offline',
        ]);

        return [
            'authorize_url' => $type->oauth['authorize_url'].'?'.$query,
            'state' => $state,
        ];
    }

    /**
     * Complete the flow: exchange the code for tokens and store a credential.
     */
    public function handleCallback(string $state, string $code): Credential
    {
        $stateRecord = OAuthCredentialState::where('state', $state)->first();

        if (! $stateRecord || $stateRecord->isExpired()) {
            throw new RuntimeException('Invalid or expired OAuth state.');
        }

        $type = $this->resolveOAuthType($stateRecord->credential_type_key);
        $client = $this->clientConfig($stateRecord->credential_type_key);

        $response = Http::asForm()->post($type->oauth['token_url'], [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $client['client_id'],
            'client_secret' => $client['client_secret'],
            'redirect_uri' => $stateRecord->redirect_uri,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("Token exchange failed: HTTP {$response->status()}.");
        }

        $tokens = $response->json();

        $credential = Credential::create([
            'workspace_id' => $stateRecord->workspace_id,
            'created_by' => $stateRecord->user_id,
            'name' => $stateRecord->name,
            'type' => $stateRecord->credential_type_key,
            'data' => encrypt(json_encode([
                'type' => 'oauth2',
                'access_token' => $tokens['access_token'] ?? null,
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'expires_in' => $tokens['expires_in'] ?? null,
                'scope' => $tokens['scope'] ?? implode(' ', $stateRecord->scopes ?? []),
                'obtained_at' => now()->toISOString(),
            ])),
            'expires_at' => isset($tokens['expires_in']) ? now()->addSeconds((int) $tokens['expires_in']) : null,
        ]);

        $stateRecord->delete();

        return $credential;
    }

    /**
     * Refresh an OAuth2 credential's access token using its refresh token.
     */
    public function refresh(Credential $credential): bool
    {
        $data = $credential->getDecryptedData();
        $refreshToken = $data['refresh_token'] ?? null;

        if (! $refreshToken) {
            return false;
        }

        $type = $this->resolveOAuthType($credential->type);
        $client = $this->clientConfig($credential->type);

        $response = Http::asForm()->post($type->oauth['token_url'], [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $client['client_id'],
            'client_secret' => $client['client_secret'],
        ]);

        if (! $response->successful()) {
            return false;
        }

        $tokens = $response->json();

        $credential->update([
            'data' => encrypt(json_encode(array_merge($data, [
                'access_token' => $tokens['access_token'] ?? $data['access_token'] ?? null,
                'refresh_token' => $tokens['refresh_token'] ?? $refreshToken,
                'expires_in' => $tokens['expires_in'] ?? null,
                'obtained_at' => now()->toISOString(),
            ]))),
            'expires_at' => isset($tokens['expires_in']) ? now()->addSeconds((int) $tokens['expires_in']) : null,
        ]);

        return true;
    }

    private function resolveOAuthType(string $key): CredentialType
    {
        $type = CredentialType::where('key', $key)->where('auth_type', 'oauth2')->first();

        if (! $type || empty($type->oauth['authorize_url']) || empty($type->oauth['token_url'])) {
            throw new RuntimeException("Credential type [{$key}] is not a configured OAuth2 provider.");
        }

        return $type;
    }

    /**
     * @return array{client_id: string, client_secret: string}
     */
    private function clientConfig(string $key): array
    {
        $config = config("services.{$key}");

        if (empty($config['client_id']) || empty($config['client_secret'])) {
            throw new RuntimeException("OAuth client credentials for [{$key}] are not configured.");
        }

        return ['client_id' => $config['client_id'], 'client_secret' => $config['client_secret']];
    }

    private function callbackUri(): string
    {
        return route('v1.oauth-credentials.callback');
    }
}
