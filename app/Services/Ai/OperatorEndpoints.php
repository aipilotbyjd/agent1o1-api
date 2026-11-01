<?php

namespace App\Services\Ai;

use App\Models\Ai\AiEndpoint;
use Laravel\Ai\AiManager;

/**
 * Makes the operator's own endpoints (`AiEndpoint`) look like any other AI
 * provider: each enabled one is put into `config('ai.providers')` under its
 * slug the first time something asks for it, so everything that already
 * works from that config (the resolver's availability check, BYOK's base
 * config, the SDK itself) needs no change and a deploy is never needed to
 * add a provider.
 *
 * Only a provider `config/ai.php` does not define is ever looked up, so the
 * usual providers cost no query. A registered endpoint is re-read at most
 * every `RECHECK_SECONDS`, which is how a long-running worker notices a
 * changed key or a switched-off endpoint without a restart.
 */
class OperatorEndpoints
{
    private const int RECHECK_SECONDS = 30;

    /**
     * @var array<string, array{dynamic: bool, at: int, limits?: array{workspace: ?int, total: ?int}|null}>
     */
    private array $seen = [];

    public function __construct(private readonly AiManager $ai) {}

    public function ensureRegistered(string $provider): void
    {
        $known = $this->seen[$provider] ?? null;

        if ($known === null && config("ai.providers.{$provider}") !== null) {
            $this->seen[$provider] = ['dynamic' => false, 'at' => 0];

            return;
        }

        if ($known !== null && (! $known['dynamic'] || now()->getTimestamp() - $known['at'] < self::RECHECK_SECONDS)) {
            return;
        }

        $endpoint = AiEndpoint::query()->where('slug', $provider)->where('is_enabled', true)->first();

        $endpoint !== null ? config(["ai.providers.{$provider}" => $endpoint->providerConfig()]) : $this->unregister($provider);

        // The SDK keeps the provider it built, so a changed key or URL has to drop it.
        $this->ai->purge($provider);

        $this->seen[$provider] = ['dynamic' => true, 'at' => now()->getTimestamp(), 'limits' => $endpoint?->rateLimits()];
    }

    /**
     * The per-minute request limits of an operator endpoint, or null for a
     * provider that has none (every provider `config/ai.php` defines, and an
     * endpoint with no limits set).
     *
     * @return array{workspace: ?int, total: ?int}|null
     */
    public function limitsFor(string $provider): ?array
    {
        $this->ensureRegistered($provider);

        return $this->seen[$provider]['limits'] ?? null;
    }

    /**
     * Takes a provider out of the config altogether (rather than setting it to
     * null, which would leave its name behind in every list of providers).
     */
    private function unregister(string $provider): void
    {
        $providers = (array) config('ai.providers');
        unset($providers[$provider]);

        config(['ai.providers' => $providers]);
    }

    /**
     * Drops what this process knows, so the next use re-reads the endpoint:
     * for a change made in this very request.
     */
    public function refresh(string $provider): void
    {
        if ($this->seen[$provider]['dynamic'] ?? false) {
            $this->seen[$provider]['at'] = 0;

            return;
        }

        if (config("ai.providers.{$provider}") === null) {
            unset($this->seen[$provider]);
        }
    }

    /**
     * Provider names an endpoint may not take: those `config/ai.php` defines
     * (an endpoint would otherwise be shadowed by, or shadow, the real
     * provider) and anything that looks like a workspace's own key.
     *
     * @return array<int, string>
     */
    public function reservedSlugs(): array
    {
        $dynamic = array_keys(array_filter($this->seen, fn (array $entry): bool => $entry['dynamic']));
        $endpointSlugs = AiEndpoint::query()->pluck('slug')->all();

        return array_values(array_unique([
            ...array_diff(array_keys(array_filter((array) config('ai.providers'))), $dynamic, $endpointSlugs),
            ...array_keys((array) config('byok.providers')),
        ]));
    }

    public static function slugIsByokShaped(string $slug): bool
    {
        return str_starts_with($slug, ByokProviderRegistrar::PREFIX);
    }
}
