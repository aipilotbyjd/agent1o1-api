<?php

namespace App\Services\Ai\Connect;

use App\Enums\Ai\AiProviderAuthMethod;
use Illuminate\Support\Carbon;

/**
 * What a provider handed back for a workspace: the secret every call is
 * made with (`apiKey`, an API key or an OAuth access token), and for an
 * OAuth token what is needed to renew it.
 */
final readonly class ConnectedAiCredential
{
    public function __construct(
        public string $apiKey,
        public AiProviderAuthMethod $authMethod,
        public ?string $refreshToken = null,
        public ?Carbon $expiresAt = null,
    ) {}

    /**
     * The encrypted `data` of an `AiProviderCredential`.
     *
     * @return array{api_key: string, refresh_token?: string}
     */
    public function data(): array
    {
        return array_filter([
            'api_key' => $this->apiKey,
            'refresh_token' => $this->refreshToken,
        ], fn (?string $value): bool => $value !== null && $value !== '');
    }
}
