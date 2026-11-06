<?php

namespace App\Actions\Ai;

use App\Enums\Ai\AiProviderAuthMethod;
use App\Enums\Ai\AiProviderCredentialStatus;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Models\Ai\AiProviderCredential;
use App\Models\Workspaces\Workspace;
use App\Services\Ai\AiProviderKeyChecker;
use App\Services\Ai\Connect\ConnectedAiCredential;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores a workspace's own AI provider credential: the one place a key
 * becomes a row, whether it was pasted in or came from one-click connect.
 * The key is checked with the provider first. One the provider rejects is
 * refused outright; one it couldn't be reached about is stored unvalidated
 * and checked again in the background. The first key in its group becomes
 * the one that's used, so adding a key is all it takes to start using it.
 */
class StoreAiProviderCredentialAction
{
    public function __construct(private readonly AiProviderKeyChecker $checker) {}

    /**
     * @return array{credential: AiProviderCredential, message: string}
     *
     * @throws ValidationException when the provider rejects the key
     */
    public function execute(
        Workspace $workspace,
        string $createdBy,
        string $provider,
        ConnectorCredentialScope $scope,
        ConnectedAiCredential $connected,
        ?string $name = null,
        bool $makeDefault = false,
    ): array {
        $result = $this->checker->check($provider, $connected->apiKey);

        if ($result['rejected']) {
            throw ValidationException::withMessages(['api_key' => $result['message']]);
        }

        $credential = $workspace->aiProviderCredentials()->create([
            'created_by' => $createdBy,
            'execution_provider' => $provider,
            'scope' => $scope,
            'name' => $name ?: null,
            'data' => $connected->data(),
            'auth_method' => $connected->authMethod,
            'token_expires_at' => $connected->expiresAt,
            'key_hint' => AiProviderCredential::hintFor($connected->apiKey),
            'validation_status' => $result['ok'] ? AiProviderCredentialStatus::Valid : AiProviderCredentialStatus::Unvalidated,
            'validation_message' => Str::limit($result['message'], 497),
            'last_validated_at' => now(),
        ]);

        if ($makeDefault || ! $credential->groupHasDefault()) {
            $credential->markAsDefault();
        }

        return ['credential' => $credential->fresh(), 'message' => $result['message']];
    }

    /**
     * A pasted key, as `execute()` takes it.
     */
    public static function pastedKey(string $apiKey): ConnectedAiCredential
    {
        return new ConnectedAiCredential(trim($apiKey), AiProviderAuthMethod::ApiKey);
    }
}
