<?php

namespace App\Services\Ai\Connect;

use App\Contracts\Ai\AiProviderConnector;

/**
 * Finds the one-click connector for a provider, from its
 * `config('byok.providers.*.connect.driver')`.
 */
class AiProviderConnectors
{
    /**
     * The provider's connector, or null when it has none or it is switched off.
     */
    public function enabled(string $provider): ?AiProviderConnector
    {
        $connector = $this->for($provider);

        return $connector?->isEnabled() ? $connector : null;
    }

    /**
     * The provider's connector whether or not it is switched on, so a token
     * already connected can still be renewed after connect is turned off.
     */
    public function for(string $provider): ?AiProviderConnector
    {
        return match (config("byok.providers.{$provider}.connect.driver")) {
            'openrouter' => new OpenRouterConnector,
            'oauth' => new OAuthAiProviderConnector($provider),
            default => null,
        };
    }
}
