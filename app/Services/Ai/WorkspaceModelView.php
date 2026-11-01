<?php

namespace App\Services\Ai;

use App\Enums\Ai\PlatformKeyUsage;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Models\Ai\WorkspaceAiKeyPolicy;
use App\Models\Ai\WorkspaceModelSettings;
use App\Services\Billing\CreditMeter;

/**
 * How the catalog looks from one member's seat in one workspace: whether a
 * model can run and, if not, why and what would fix it; whether it runs on
 * the workspace's own key; whether an admin allows it or made it the
 * default; and roughly what a message costs. Mirrors the order
 * `ByokProviderRegistrar` tries a model's hops in, without registering
 * anything. Never reveals a route's model id, only which providers a key
 * could be added for.
 */
class WorkspaceModelView
{
    /**
     * @param  array<int, string>  $ownKeyProviders
     */
    private function __construct(
        private readonly array $ownKeyProviders,
        private readonly PlatformKeyUsage $platformUsage,
        private readonly ?WorkspaceModelSettings $settings,
        private readonly CreditMeter $meter,
        private readonly ?int $planCredits = null,
    ) {}

    public static function forMember(string $workspaceId, string $userId): self
    {
        return new self(
            app(ByokProviderRegistrar::class)->coveredProviders($workspaceId, $userId),
            WorkspaceAiKeyPolicy::forWorkspace($workspaceId)->platform_usage,
            WorkspaceModelSettings::forWorkspace($workspaceId),
            app(CreditMeter::class),
            WorkspaceModels::planCredits($workspaceId),
        );
    }

    /**
     * Outside any workspace: the platform's keys only, and no plan to limit by.
     */
    public static function platform(): self
    {
        return new self([], PlatformKeyUsage::Fallback, null, app(CreditMeter::class));
    }

    /**
     * `$entry->routes` must be loaded with only its enabled routes.
     *
     * @return array{
     *     is_available: bool,
     *     unavailable_reason: ?string,
     *     connect_providers: array<int, array{key: string, label: string}>,
     *     own_key: ?array{provider: string, label: string},
     *     is_allowed: bool,
     *     is_default: bool,
     *     credits_per_message: ?int,
     *     required_plan: ?array{slug: string, name: string},
     * }
     */
    public function describe(ModelCatalog $entry): array
    {
        $routes = $entry->routes;
        $covered = $routes->contains(fn (ModelRoute $route): bool => $this->ownsKeyFor($route));
        $platformAllowed = match ($this->platformUsage) {
            PlatformKeyUsage::Fallback => true,
            PlatformKeyUsage::WhenNoKey => ! $covered,
            PlatformKeyUsage::Never => false,
        };

        $serving = $routes->first(fn (ModelRoute $route): bool => $this->ownsKeyFor($route)
            || ($platformAllowed && ModelCatalogResolver::providerIsConfigured($route->execution_provider)));

        $isAllowed = $this->settings?->allows($entry->id) ?? true;
        $requiredPlan = $entry->minPlan;
        $planBlocked = $requiredPlan !== null && $this->planCredits !== null && $this->planCredits < $requiredPlan->creditsMonthly();

        $connectable = $routes
            ->pluck('execution_provider')
            ->unique()
            ->filter(fn (string $provider): bool => config("byok.providers.{$provider}") !== null)
            ->map(fn (string $provider): array => ['key' => $provider, 'label' => (string) config("byok.providers.{$provider}.label")])
            ->values()
            ->all();

        $reason = match (true) {
            ! $isAllowed => 'not_allowed',
            $planBlocked => 'plan_required',
            $serving !== null => null,
            $this->platformUsage === PlatformKeyUsage::Never && $connectable !== [] => 'policy_needs_key',
            $connectable !== [] => 'needs_key',
            default => 'unavailable',
        };

        return [
            'is_available' => $reason === null,
            'unavailable_reason' => $reason,
            'connect_providers' => $reason === 'needs_key' || $reason === 'policy_needs_key' ? $connectable : [],
            'own_key' => $serving !== null && $this->ownsKeyFor($serving)
                ? ['provider' => $serving->execution_provider, 'label' => (string) config("byok.providers.{$serving->execution_provider}.label")]
                : null,
            'is_allowed' => $isAllowed,
            'is_default' => $this->settings?->default_model_catalog_id === $entry->id,
            'credits_per_message' => $serving === null ? null : $this->meter->estimateChatMessage(
                $this->ownsKeyFor($serving) ? ByokProviderRegistrar::PREFIX.'estimate' : $serving->execution_provider,
                $serving->execution_model_id,
            ),
            'required_plan' => $requiredPlan === null ? null : ['slug' => $requiredPlan->slug, 'name' => $requiredPlan->name],
        ];
    }

    private function ownsKeyFor(ModelRoute $route): bool
    {
        return in_array($route->execution_provider, $this->ownKeyProviders, true);
    }
}
