<?php

namespace App\Services\Ai\Connect;

use App\Actions\Ai\StoreAiProviderCredentialAction;
use App\Authorization\WorkspaceContext;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Workspaces\Permission;
use App\Exceptions\AiProviderConnectException;
use App\Models\Ai\AiProviderConnectState;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\WorkspaceAiKeyPolicy;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Str;

/**
 * One-click connect for a workspace's own AI provider key, for every
 * provider with a `connect` block in `config/byok.php`:
 *
 * 1. `start()` picks whose key it will be, stores a short-lived `state` with
 *    a fresh PKCE verifier, and returns the provider's sign-in URL.
 * 2. The member signs in and approves; the provider sends their browser to
 *    the frontend's `byok.connect.redirect_path` page with `code` + `state`.
 * 3. That page, signed in as the member, hands both to `complete()`, which
 *    claims the state (once), checks it is the same member who started the
 *    connection and that they may still add the key, trades the code for a
 *    credential, and stores it as the default key of its group.
 *
 * The same-member check is what stops a sign-in link being used against
 * someone else: without it, a link started in one workspace and approved
 * by another person would put a key on that person's provider account
 * into the first workspace.
 *
 * Whose key: a member who can manage the workspace's keys (owner, admin)
 * adds a team key; anyone else adds a personal key, when the workspace
 * allows those.
 */
class AiProviderConnectFlow
{
    public function __construct(
        private readonly AiProviderConnectors $connectors,
        private readonly StoreAiProviderCredentialAction $store,
    ) {}

    /**
     * @return array{authorize_url: string, scope: string}
     */
    public function start(Workspace $workspace, User $user, string $provider): array
    {
        $connector = $this->connectors->enabled($provider)
            ?? throw AiProviderConnectException::notAvailable($this->providerLabel($provider));
        $scope = $this->scopeFor($workspace, $user) ?? throw AiProviderConnectException::notAllowed();

        AiProviderConnectState::query()->where('expires_at', '<', now()->subHour())->delete();

        $state = Str::random(40);
        $codeVerifier = Str::random(64);
        $redirectUri = $this->redirectUri();

        AiProviderConnectState::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'execution_provider' => $provider,
            'scope' => $scope,
            'state' => $state,
            'code_verifier' => $codeVerifier,
            'redirect_uri' => $redirectUri,
            'expires_at' => now()->addMinutes((int) config('byok.connect.state_ttl_minutes', 10)),
        ]);

        return [
            'authorize_url' => $connector->authorizeUrl($state, self::codeChallenge($codeVerifier), $redirectUri),
            'scope' => $scope->value,
        ];
    }

    /**
     * @throws AiProviderConnectException
     */
    public function complete(string $state, string $code, User $user): AiProviderCredential
    {
        $pending = $state === '' ? null : AiProviderConnectState::query()->where('state', $state)->first();

        if ($pending === null || $pending->expires_at->isPast() || $code === '') {
            throw AiProviderConnectException::expired();
        }

        // Someone else's sign-in link: burn it, so it can't be tried again.
        if ($pending->user_id !== $user->id) {
            AiProviderConnectState::query()->whereKey($pending->getKey())->delete();

            throw AiProviderConnectException::startedByAnotherAccount();
        }

        // Single use, claimed before any network call: the delete only
        // succeeds for one of two simultaneous callbacks with the same state.
        if (AiProviderConnectState::query()->whereKey($pending->getKey())->delete() === 0) {
            throw AiProviderConnectException::expired();
        }

        $workspace = $pending->workspace;

        if ($workspace === null) {
            throw AiProviderConnectException::expired();
        }

        // The member's role or the workspace's policy may have changed while
        // they were away at the provider.
        if (! $this->mayAdd($workspace, $user, $pending->scope)) {
            throw AiProviderConnectException::notAllowed();
        }

        $connector = $this->connectors->enabled($pending->execution_provider)
            ?? throw AiProviderConnectException::notAvailable($this->providerLabel($pending->execution_provider));

        $connected = $connector->exchange($code, $pending->code_verifier, $pending->redirect_uri);

        return $this->store->execute(
            $workspace,
            $user->id,
            $pending->execution_provider,
            $pending->scope,
            $connected,
            makeDefault: true,
        )['credential'];
    }

    /**
     * The scope a one-click key from this member is added with, or null
     * when they may not add one.
     */
    public function scopeFor(Workspace $workspace, User $user): ?ConnectorCredentialScope
    {
        foreach ([ConnectorCredentialScope::Team, ConnectorCredentialScope::Personal] as $scope) {
            if ($this->mayAdd($workspace, $user, $scope)) {
                return $scope;
            }
        }

        return null;
    }

    /**
     * The PKCE S256 challenge for a verifier (RFC 7636 §4.2).
     */
    public static function codeChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }

    private function mayAdd(Workspace $workspace, User $user, ConnectorCredentialScope $scope): bool
    {
        $role = WorkspaceContext::resolveRole($workspace, $user);

        return match ($scope) {
            ConnectorCredentialScope::Team => $role?->has(Permission::AiCredentialManage) ?? false,
            ConnectorCredentialScope::Personal => ($role?->has(Permission::AiCredentialUsePersonal) ?? false)
                && WorkspaceAiKeyPolicy::forWorkspace($workspace->id)->allow_personal_keys,
        };
    }

    private function redirectUri(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/'.ltrim((string) config('byok.connect.redirect_path'), '/');
    }

    private function providerLabel(string $provider): string
    {
        return (string) config("byok.providers.{$provider}.label", Str::headline($provider));
    }
}
