<?php

namespace App\Services\Ai\Operations;

use App\Enums\Ai\AiProviderCredentialStatus;
use App\Jobs\Workflows\ProcessWorkflowBuilderMessageJob;
use App\Models\Ai\AiEndpoint;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelPrice;
use App\Models\Ai\ModelRoute;
use App\Services\Ai\EndpointRateLimiter;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Ai\ModelPrices;
use App\Services\Ai\ProviderPauses;
use Illuminate\Support\Collection;

/**
 * Everything wrong (or about to be) with the model setup, in one list: the
 * report an operator reads instead of checking each model, route, key and
 * price by hand. Errors mean something is broken now, warnings mean a risk
 * (a model you pay for with no price, a route that keeps failing), and info
 * is worth knowing. Nothing here changes data.
 */
class ModelHealthReport
{
    public const string ERROR = 'error';

    public const string WARNING = 'warning';

    public const string INFO = 'info';

    /** A price not confirmed for this long is called stale. */
    public const int STALE_PRICE_DAYS = 90;

    /** Failures in a row, within a day, before a route is called out. */
    public const int FAILING_ROUTE_THRESHOLD = 3;

    /** How far ahead an announced retirement is worth mentioning. */
    public const int RETIRING_SOON_DAYS = 30;

    public function __construct(
        private readonly ModelPrices $prices,
        private readonly SpendGuard $spend,
        private readonly ProviderPauses $pauses,
        private readonly EndpointRateLimiter $limits,
    ) {}

    /**
     * @return array{healthy: bool, summary: array{errors: int, warnings: int, info: int}, findings: list<array{severity: string, code: string, message: string, subject: array{type: string, slug: ?string, id: ?string}}>}
     */
    public function run(): array
    {
        $models = ModelCatalog::query()->with('routes')->orderBy('slug')->get();
        $endpoints = AiEndpoint::query()->get()->keyBy('slug');
        $findings = collect();

        foreach ($this->requiredInternalSlugs() as $slug) {
            if (! $models->contains('slug', $slug)) {
                $findings->push($this->finding(self::ERROR, 'internal_model_missing', "The internal model [{$slug}] does not exist, so the feature that depends on it will fail.", 'system'));
            }
        }

        foreach ($models as $model) {
            $this->checkReplacement($model, $models, $findings);

            if ($model->is_active) {
                $this->checkModel($model, $endpoints, $findings);
            }
        }

        $this->checkPausedProviders($models, $findings);
        $this->checkEmbeddings($findings);
        $this->checkEndpoints($endpoints, $models, $findings);
        $this->checkByokKeys($findings);
        $this->checkSpend($findings);

        $sorted = $findings
            ->sortBy(fn (array $finding): array => [
                [self::ERROR => 0, self::WARNING => 1, self::INFO => 2][$finding['severity']],
                $finding['subject']['slug'] ?? '',
                $finding['code'],
            ])
            ->values();

        $summary = [
            'errors' => $sorted->where('severity', self::ERROR)->count(),
            'warnings' => $sorted->where('severity', self::WARNING)->count(),
            'info' => $sorted->where('severity', self::INFO)->count(),
        ];

        return ['healthy' => $summary['errors'] === 0, 'summary' => $summary, 'findings' => $sorted->all()];
    }

    /**
     * @param  Collection<string, AiEndpoint>  $endpoints
     * @param  Collection<int, array<string, mixed>>  $findings
     */
    private function checkModel(ModelCatalog $model, Collection $endpoints, Collection $findings): void
    {
        $enabled = $model->routes->where('is_enabled', true)->values();

        if ($enabled->isEmpty()) {
            $findings->push($this->finding(self::ERROR, 'no_enabled_route', "[{$model->slug}] is active but has no enabled route, so every request for it fails.", 'model', $model));

            return;
        }

        $usable = $enabled->filter(fn (ModelRoute $route): bool => ModelCatalogResolver::providerIsConfigured($route->execution_provider));
        $byokOnly = $enabled->reject(fn (ModelRoute $route): bool => ModelCatalogResolver::providerIsConfigured($route->execution_provider))
            ->filter(fn (ModelRoute $route): bool => config("byok.providers.{$route->execution_provider}") !== null);

        $paused = $enabled->filter(fn (ModelRoute $route): bool => $this->pauses->isPaused($route->execution_provider)
            && ModelCatalogResolver::providerIsConfigured($route->execution_provider, ignoringPause: true));

        if ($usable->isEmpty() && $paused->isNotEmpty()) {
            $findings->push($this->finding(self::WARNING, 'paused_routes_only', "[{$model->slug}] cannot run right now: ".$paused->pluck('execution_provider')->unique()->implode(', ').' is paused.', 'model', $model));
        } elseif ($usable->isEmpty()) {
            $findings->push($byokOnly->isNotEmpty()
                ? $this->finding(self::INFO, 'byok_only', "[{$model->slug}] can only be run by workspaces that bring their own key: the platform has none.", 'model', $model)
                : $this->finding(self::ERROR, 'no_usable_route', "None of [{$model->slug}]'s enabled routes can run: the platform has no key for ".$enabled->pluck('execution_provider')->unique()->implode(', ').'.', 'model', $model));
        }

        foreach ($enabled as $route) {
            $this->checkRoute($model, $route, $usable->contains('id', $route->id), $usable->isNotEmpty(), $endpoints, $findings);
        }

        $this->checkRetirement($model, $findings);
    }

    /**
     * @param  Collection<string, AiEndpoint>  $endpoints
     * @param  Collection<int, array<string, mixed>>  $findings
     */
    private function checkRoute(ModelCatalog $model, ModelRoute $route, bool $isUsable, bool $modelHasUsable, Collection $endpoints, Collection $findings): void
    {
        $label = "{$route->execution_provider}:{$route->execution_model_id}";

        if (! $isUsable) {
            // A paused provider is reported once for the provider, not once per route.
            if ($this->pauses->isPaused($route->execution_provider)) {
                return;
            }

            $isByok = config("byok.providers.{$route->execution_provider}") !== null;

            if ($modelHasUsable && ! $isByok) {
                $findings->push($this->finding(self::WARNING, 'route_provider_not_configured', "The route {$label} of [{$model->slug}] is enabled but the platform has no key for it, so it is skipped.", 'route', $model, $route));
            }

            return;
        }

        $price = $this->prices->for($route->execution_provider, $route->execution_model_id);
        $stored = $this->prices->isStored($route->execution_provider, $route->execution_model_id);

        if ($price === null) {
            $findings->push($this->finding(self::WARNING, 'route_unpriced', "{$label} has no price, so calls to [{$model->slug}] are billed by a flat guess instead of what they cost you.", 'route', $model, $route));
        } elseif (! $stored) {
            $findings->push($this->finding(self::INFO, 'price_not_in_table', "{$label} is priced only in config/billing.php. Import it so it can be managed.", 'route', $model, $route));
        } else {
            $row = ModelPrice::query()->where('provider', $route->execution_provider)->where('model_id', $route->execution_model_id)->first();

            if ($row?->checked_at === null) {
                $findings->push($this->finding(self::INFO, 'price_unverified', "The price of {$label} was never confirmed against the provider.", 'route', $model, $route));
            } elseif ($row->checked_at->lt(now()->subDays(self::STALE_PRICE_DAYS))) {
                $findings->push($this->finding(self::WARNING, 'price_stale', "The price of {$label} was last confirmed ".$row->checked_at->diffForHumans().'.', 'route', $model, $route));
            }
        }

        if ($route->failure_count >= self::FAILING_ROUTE_THRESHOLD && $route->last_failed_at?->gt(now()->subDay())) {
            $findings->push($this->finding(self::WARNING, 'route_failing', "{$label} has failed {$route->failure_count} times, most recently ".$route->last_failed_at->diffForHumans().'.', 'route', $model, $route));
        }

        if ($route->last_check_ok === false) {
            $findings->push($this->finding(self::WARNING, 'route_check_failed', "The last test of {$label} failed: ".($route->last_check_message ?? 'no detail').'.', 'route', $model, $route));
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $findings
     */
    private function checkRetirement(ModelCatalog $model, Collection $findings): void
    {
        if ($model->retires_at === null) {
            return;
        }

        if ($model->retires_at->isPast() && blank($model->replacement_slug)) {
            $findings->push($this->finding(self::ERROR, 'past_retirement', "[{$model->slug}] passed its retirement date and has no replacement, so it cannot be retired automatically.", 'model', $model));

            return;
        }

        if ($model->retires_at->isFuture() && $model->retires_at->lte(now()->addDays(self::RETIRING_SOON_DAYS))) {
            $findings->push(blank($model->replacement_slug)
                ? $this->finding(self::WARNING, 'retiring_without_replacement', "[{$model->slug}] retires ".$model->retires_at->diffForHumans().' with no replacement named.', 'model', $model)
                : $this->finding(self::INFO, 'retiring_soon', "[{$model->slug}] retires ".$model->retires_at->diffForHumans()." in favour of [{$model->replacement_slug}].", 'model', $model));
        }
    }

    /**
     * @param  Collection<int, ModelCatalog>  $models
     * @param  Collection<int, array<string, mixed>>  $findings
     */
    private function checkReplacement(ModelCatalog $model, Collection $models, Collection $findings): void
    {
        if (blank($model->replacement_slug)) {
            return;
        }

        $replacement = $models->firstWhere('slug', $model->replacement_slug);

        if ($replacement === null || $replacement->isRetired()) {
            $findings->push($this->finding(self::ERROR, 'replacement_unavailable', "[{$model->slug}] is to be replaced by [{$model->replacement_slug}], which ".($replacement === null ? 'does not exist' : 'is itself retired').'.', 'model', $model));
        }
    }

    /**
     * @param  Collection<int, ModelCatalog>  $models
     * @param  Collection<int, array<string, mixed>>  $findings
     */
    private function checkPausedProviders(Collection $models, Collection $findings): void
    {
        foreach ($this->pauses->all() as $provider => $reason) {
            $affected = $models->filter(fn (ModelCatalog $model): bool => $model->is_active
                && $model->routes->contains(fn (ModelRoute $route): bool => $route->is_enabled && $route->execution_provider === $provider));

            $findings->push($this->finding(
                self::WARNING,
                'provider_paused',
                "The provider [{$provider}] is paused ({$reason})".($affected->isEmpty() ? '.' : '; '.$affected->count().' active model(s) route to it.'),
                'system',
            ));
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $findings
     */
    private function checkEmbeddings(Collection $findings): void
    {
        $provider = (string) config('ai.default_for_embeddings');

        if (! ModelCatalogResolver::providerIsConfigured($provider)) {
            $findings->push($this->finding(self::ERROR, 'embeddings_provider_not_configured', "The embeddings provider [{$provider}] has no key, so knowledge bases cannot be searched or synced.", 'system'));
        }
    }

    /**
     * @param  Collection<string, AiEndpoint>  $endpoints
     * @param  Collection<int, ModelCatalog>  $models
     * @param  Collection<int, array<string, mixed>>  $findings
     */
    private function checkEndpoints(Collection $endpoints, Collection $models, Collection $findings): void
    {
        foreach ($endpoints as $endpoint) {
            $routed = $models->filter(fn (ModelCatalog $model): bool => $model->is_active
                && $model->routes->contains(fn (ModelRoute $route): bool => $route->is_enabled && $route->execution_provider === $endpoint->slug));

            if (! $endpoint->is_enabled && $routed->isNotEmpty()) {
                $findings->push($this->finding(self::ERROR, 'endpoint_disabled_but_routed', "The endpoint [{$endpoint->slug}] is switched off but [".$routed->pluck('slug')->implode(', ').'] still route to it.', 'endpoint', endpoint: $endpoint));
            }

            $turnedAway = $endpoint->rateLimits() === null ? 0 : $this->limits->rejectedToday($endpoint->slug);

            if ($turnedAway > 0) {
                $findings->push($this->finding(self::INFO, 'endpoint_rate_limited', "The request limits on the endpoint [{$endpoint->slug}] turned away {$turnedAway} call(s) today. Raise a limit if that is more than you want, or add capacity.", 'endpoint', endpoint: $endpoint));
            }

            if ($endpoint->is_enabled && $endpoint->last_check_ok === false) {
                $findings->push($this->finding(self::WARNING, 'endpoint_check_failed', "The last test of the endpoint [{$endpoint->slug}] failed: ".($endpoint->last_check_message ?? 'no detail').'.', 'endpoint', endpoint: $endpoint));
            }
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $findings
     */
    private function checkByokKeys(Collection $findings): void
    {
        $rejected = AiProviderCredential::query()->where('validation_status', AiProviderCredentialStatus::Invalid->value)->count();

        if ($rejected > 0) {
            $findings->push($this->finding(self::INFO, 'byok_rejected_keys', "{$rejected} workspace key(s) were rejected by their provider and are out of use.", 'system'));
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $findings
     */
    private function checkSpend(Collection $findings): void
    {
        foreach ($this->spend->findings() as $finding) {
            $findings->push([
                'severity' => self::WARNING,
                'code' => $finding['code'],
                'message' => $finding['message'],
                'subject' => [
                    'type' => $finding['workspace_id'] === null ? 'system' : 'workspace',
                    'slug' => null,
                    'id' => $finding['workspace_id'],
                ],
            ]);
        }
    }

    /**
     * The internal models the platform's own features are hard-wired to.
     *
     * @return array<int, string>
     */
    private function requiredInternalSlugs(): array
    {
        return array_values(array_unique(array_filter([
            ProcessWorkflowBuilderMessageJob::MODEL_CATALOG_SLUG,
            (string) config('assistant.runtime.catalog'),
        ])));
    }

    /**
     * @return array{severity: string, code: string, message: string, subject: array{type: string, slug: ?string, id: ?string}}
     */
    private function finding(string $severity, string $code, string $message, string $type, ?ModelCatalog $model = null, ?ModelRoute $route = null, ?AiEndpoint $endpoint = null): array
    {
        return [
            'severity' => $severity,
            'code' => $code,
            'message' => $message,
            'subject' => [
                'type' => $type,
                'slug' => $model?->slug ?? $endpoint?->slug,
                'id' => $route?->id ?? $model?->id ?? $endpoint?->id,
            ],
        ];
    }
}
