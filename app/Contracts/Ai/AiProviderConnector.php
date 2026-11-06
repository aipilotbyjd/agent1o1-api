<?php

namespace App\Contracts\Ai;

use App\Exceptions\AiProviderConnectException;
use App\Models\Ai\AiProviderCredential;
use App\Services\Ai\Connect\ConnectedAiCredential;

/**
 * One-click connect for one AI provider: the member signs in at the
 * provider and comes back with a credential for their workspace, nothing
 * to copy. Driven by `AiProviderConnectFlow`; implementations are picked by
 * `config('byok.providers.*.connect.driver')` in `AiProviderConnectors`.
 * Every flow uses PKCE (S256), so the authorization code is useless to
 * anyone but the server that asked for it.
 */
interface AiProviderConnector
{
    /**
     * The `config/byok.php` provider key this connects (`openrouter`, ...).
     */
    public function provider(): string;

    /**
     * Whether one-click connect is switched on (`AiProviderSettings`) and
     * configured here.
     */
    public function isEnabled(): bool;

    /**
     * Whether it could run if switched on: a login app is set, for a
     * provider that needs one.
     */
    public function isConfigured(): bool;

    /**
     * What the button says, e.g. "Connect with OpenRouter".
     */
    public function label(): string;

    /**
     * Where to send the member's browser to sign in and approve.
     */
    public function authorizeUrl(string $state, string $codeChallenge, string $redirectUri): string;

    /**
     * Trades the code the provider sent back for a credential.
     *
     * @throws AiProviderConnectException when the provider refuses
     */
    public function exchange(string $code, string $codeVerifier, string $redirectUri): ConnectedAiCredential;

    /**
     * Renews an expiring OAuth credential with its refresh token.
     *
     * @throws AiProviderConnectException when it can't be renewed (reconnect needed)
     */
    public function refresh(AiProviderCredential $credential): ConnectedAiCredential;

    /**
     * Settings the SDK provider built for this credential should use instead
     * of the static `config/ai.php` entry's (`['store' => false]`).
     *
     * @return array<string, mixed>
     */
    public function providerOverrides(): array;
}
