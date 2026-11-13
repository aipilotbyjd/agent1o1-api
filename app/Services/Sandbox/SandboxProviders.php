<?php

namespace App\Services\Sandbox;

use App\Enums\Sandbox\SandboxDriverType;
use App\Models\Sandbox\SandboxProvider;
use Illuminate\Support\Collection;

/**
 * Every provider the operator can use: the rows in `sandbox_providers`,
 * plus the legacy `E2B_API_KEY` as an `e2b` provider until a row with that
 * key exists. That keeps a deployment that only ever set the env variable
 * working, while the panel takes over as soon as a provider is added.
 */
class SandboxProviders
{
    public const string ENV_KEY = 'e2b';

    /**
     * @return Collection<int, SandboxProvider>
     */
    public function all(): Collection
    {
        $providers = SandboxProvider::query()->orderBy('priority')->orderBy('name')->get();

        $fallback = $this->envFallback($providers);

        return $fallback !== null ? $providers->push($fallback)->values() : $providers;
    }

    /**
     * Enabled and fully set up.
     *
     * @return Collection<int, SandboxProvider>
     */
    public function usable(): Collection
    {
        return $this->all()->filter(fn (SandboxProvider $provider): bool => $provider->is_enabled && $provider->isConfigured())->values();
    }

    public function find(string $key): ?SandboxProvider
    {
        return $this->all()->first(fn (SandboxProvider $provider): bool => $provider->key === $key);
    }

    /**
     * @param  Collection<int, SandboxProvider>  $saved
     */
    private function envFallback(Collection $saved): ?SandboxProvider
    {
        if (blank(config('assistant.sandbox.api_key')) || $saved->contains('key', self::ENV_KEY)) {
            return null;
        }

        return new SandboxProvider([
            'key' => self::ENV_KEY,
            'name' => 'E2B (from E2B_API_KEY)',
            'driver' => SandboxDriverType::E2B->value,
            'is_enabled' => true,
            'priority' => 1000,
            'weight' => 1,
            'config' => [
                'api_url' => config('assistant.sandbox.api_url'),
                'domain' => config('assistant.sandbox.domain'),
                'template' => config('assistant.sandbox.template'),
            ],
            'credentials' => ['api_key' => config('assistant.sandbox.api_key')],
            'price_per_hour_usd' => 0,
        ]);
    }
}
