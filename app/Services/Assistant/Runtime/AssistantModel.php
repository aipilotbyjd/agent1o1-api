<?php

namespace App\Services\Assistant\Runtime;

use App\Models\Ai\ModelCatalog;
use App\Models\Assistant\Assistant;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Ai\ModelCatalogResolver;
use RuntimeException;

/**
 * Which model the assistant runs on, and how big its context window is.
 * The owner's catalog pick wins; then the operator's internal
 * `personal-assistant` entry; then the raw configured provider. Whichever
 * it is runs on the owner's own provider key where they have one: see
 * `ByokProviderRegistrar`.
 */
class AssistantModel
{
    public function __construct(
        private readonly ModelCatalogResolver $resolver,
        private readonly ByokProviderRegistrar $byok,
    ) {}

    /**
     * @return array{0: string|array<string, string>, 1: ?string}
     */
    public function for(Assistant $assistant): array
    {
        [$provider, $model] = $this->platformModelFor($assistant);

        return $this->byok->apply($provider, $model, $assistant->workspace_id, $assistant->user_id);
    }

    /**
     * @return array{0: string|array<string, string>, 1: ?string}
     */
    private function platformModelFor(Assistant $assistant): array
    {
        $catalog = $this->catalogFor($assistant);

        if ($catalog !== null) {
            try {
                return [$this->resolver->providerChain($catalog->slug), null];
            } catch (RuntimeException) {
                // No enabled route: fall through to the configured provider.
            }
        }

        return [(string) config('assistant.runtime.provider'), config('assistant.runtime.model')];
    }

    public function windowTokens(Assistant $assistant): int
    {
        $window = $this->catalogFor($assistant)?->capabilities['context_window'] ?? null;

        return is_numeric($window) && $window > 0 ? (int) $window : (int) config('assistant.context.default_window_tokens');
    }

    private function catalogFor(Assistant $assistant): ?ModelCatalog
    {
        if ($assistant->model_catalog_id !== null && $assistant->modelCatalog !== null) {
            return $assistant->modelCatalog;
        }

        return ModelCatalog::query()
            ->where('slug', (string) config('assistant.runtime.catalog'))
            ->where('is_active', true)
            ->first();
    }
}
