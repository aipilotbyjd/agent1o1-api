<?php

namespace App\Services\Ai\Connect;

use App\Contracts\Ai\AiProviderConnector;
use App\Enums\Ai\AiProviderAuthMethod;
use App\Exceptions\AiProviderConnectException;
use App\Models\Ai\AiProviderCredential;
use App\Services\Ai\AiProviderSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * OpenRouter's PKCE flow (https://openrouter.ai/docs/use-cases/oauth-pkce):
 * the member signs in at openrouter.ai, approves, and OpenRouter issues an
 * ordinary API key on their account. No app registration is needed, and
 * the key doesn't expire, so it is stored exactly like a pasted one.
 */
class OpenRouterConnector implements AiProviderConnector
{
    private const string PROVIDER = 'openrouter';

    public function provider(): string
    {
        return self::PROVIDER;
    }

    public function isEnabled(): bool
    {
        return app(AiProviderSettings::class)->connectEnabled(self::PROVIDER);
    }

    /**
     * OpenRouter needs no app registration.
     */
    public function isConfigured(): bool
    {
        return true;
    }

    public function label(): string
    {
        return (string) $this->config('label', 'Connect with OpenRouter');
    }

    public function authorizeUrl(string $state, string $codeChallenge, string $redirectUri): string
    {
        return $this->config('authorize_url').'?'.http_build_query([
            'callback_url' => $redirectUri,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'state' => $state,
            'key_label' => (string) $this->config('key_label', config('app.name')),
        ]);
    }

    public function exchange(string $code, string $codeVerifier, string $redirectUri): ConnectedAiCredential
    {
        try {
            $response = Http::acceptJson()->asJson()->timeout(30)->connectTimeout(10)
                ->post((string) $this->config('exchange_url'), [
                    'code' => $code,
                    'code_verifier' => $codeVerifier,
                    'code_challenge_method' => 'S256',
                ]);
        } catch (ConnectionException) {
            throw AiProviderConnectException::unreachable('OpenRouter');
        }

        $key = $response->json('key');

        if ($response->failed() || ! is_string($key) || trim($key) === '') {
            Log::warning('OpenRouter key exchange failed.', ['status' => $response->status(), 'body' => Str::limit($response->body(), 500)]);

            throw AiProviderConnectException::refused('OpenRouter', $response->status());
        }

        return new ConnectedAiCredential(trim($key), AiProviderAuthMethod::ApiKey);
    }

    /**
     * An OpenRouter key never expires, so there is nothing to renew.
     */
    public function refresh(AiProviderCredential $credential): ConnectedAiCredential
    {
        throw AiProviderConnectException::cannotRefresh('OpenRouter');
    }

    public function providerOverrides(): array
    {
        return [];
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return config('byok.providers.'.self::PROVIDER.".connect.{$key}", $default);
    }
}
