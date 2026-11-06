<?php

namespace App\Services\Ai\Operations;

use App\Enums\Ai\AiProviderCredentialStatus;
use App\Models\Ai\AiEndpoint;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\ModelRoute;
use App\Services\Ai\AiProviderSettings;
use App\Services\Ai\Connect\AiProviderConnectors;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Ai\ProviderPauses;

/**
 * Every provider the platform could send a model call to, and whether it
 * can: the ones `config/ai.php` defines (with or without a key), the
 * operator's own endpoints, and the providers only a workspace's own key can
 * serve. Reports whether a key exists, never the key, and for a provider
 * customers can bring a key for, the super admin's switches for it and how
 * many keys customers have added.
 */
class ModelProviderOverview
{
    /**
     * @return list<array{key: string, label: string, source: string, configured: bool, byok_capable: bool, is_enabled: bool, paused: bool, paused_reason: ?string, routes: int, enabled_routes: int}>
     */
    public function list(): array
    {
        $pauses = app(ProviderPauses::class)->all();
        $endpoints = AiEndpoint::query()->get()->keyBy('slug');
        $routes = ModelRoute::query()->get(['execution_provider', 'is_enabled'])->groupBy('execution_provider');
        $settings = app(AiProviderSettings::class);
        $connectors = app(AiProviderConnectors::class);
        $keys = AiProviderCredential::query()
            ->selectRaw('execution_provider, count(*) as total, sum(case when validation_status = ? then 1 else 0 end) as valid', [AiProviderCredentialStatus::Valid->value])
            ->groupBy('execution_provider')
            ->get()
            ->keyBy('execution_provider');

        $names = collect([
            ...array_keys(array_filter((array) config('ai.providers'))),
            ...$endpoints->keys()->all(),
            ...array_keys((array) config('byok.providers')),
            ...$routes->keys()->all(),
        ])->unique()->sort()->values();

        return $names->map(function (string $name) use ($endpoints, $routes, $pauses, $settings, $connectors, $keys): array {
            $endpoint = $endpoints->get($name);
            $byok = config("byok.providers.{$name}");
            $own = $routes->get($name, collect());

            return [
                'key' => $name,
                'label' => $endpoint?->name ?? ($byok['label'] ?? $name),
                'source' => match (true) {
                    $endpoint !== null => 'endpoint',
                    config("ai.providers.{$name}") !== null => 'platform',
                    default => 'own_key_only',
                },
                'configured' => ModelCatalogResolver::providerIsConfigured($name, ignoringPause: true),
                'paused' => isset($pauses[$name]),
                'paused_reason' => $pauses[$name] ?? null,
                'byok_capable' => $byok !== null,
                'is_enabled' => $endpoint?->is_enabled ?? true,
                'routes' => $own->count(),
                'enabled_routes' => $own->where('is_enabled', true)->count(),
                // Customers' own keys (`AiProviderSettings`); null for a provider they can't bring a key for.
                'byok' => $byok === null ? null : [
                    'enabled' => $settings->byokEnabled($name),
                    'featured' => $settings->featured($name),
                    'sort_order' => $settings->sortOrder($name),
                    'free_tier_note' => $settings->freeTierNote($name),
                    'disabled_message' => $settings->disabledMessage($name),
                    'keys' => ['total' => (int) ($keys[$name]->total ?? 0), 'valid' => (int) ($keys[$name]->valid ?? 0)],
                    'connect' => ($connector = $connectors->for($name)) === null ? null : [
                        'enabled' => $settings->connectEnabled($name),
                        'configured' => $connector->isConfigured(),
                        'needs_login_app' => config("byok.providers.{$name}.connect.driver") === 'oauth',
                        'login_app_stored' => $settings->hasStoredConnectClient($name),
                    ],
                ],
            ];
        })->all();
    }
}
