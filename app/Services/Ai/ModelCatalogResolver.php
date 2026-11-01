<?php

namespace App\Services\Ai;

use App\Models\Agents\Agent;
use App\Models\Ai\ModelCatalog;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Turns a public `ModelCatalog` slug into the ordered `provider => model`
 * array `laravel/ai` accepts as its `provider:` argument for automatic
 * failover (see the SDK's "Failover" docs): the one place a catalog
 * selection becomes a real execution backend. Callers (`AgentRunner`,
 * `Nodes\AiAutomation\AskAiNode`) never see individual `ModelRoute` rows,
 * only this resolved chain, so the SDK's own retry/backoff handles a
 * transient failure on the primary backend without any custom router.
 */
class ModelCatalogResolver
{
    private const int CACHE_TTL_SECONDS = 300;

    /**
     * How many replacements deep a retired model is followed (A retired for
     * B, B retired for C), so a cycle can never loop.
     */
    private const int MAX_REPLACEMENT_HOPS = 3;

    /**
     * The provider/model to prompt `$agent` with: its `provider`/`model`
     * columns, or its catalog entry's failover chain (which carries its own
     * model ids, so `model` is null). Anything prompting on an agent's behalf
     * should use this rather than reading those columns directly.
     *
     * @return array{0: string|array<string, string>, 1: ?string}
     */
    public function forAgent(Agent $agent): array
    {
        if ($agent->model_catalog_id === null) {
            return [$agent->provider, $agent->model];
        }

        return [$this->providerChain($agent->modelCatalog->slug), null];
    }

    /**
     * The provider/model to grade `$agent`'s output with. `$judgeModel` (the
     * agent's evaluation settings `model`) is a deliberate override (grade
     * with a specific model rather than the one that wrote the answer) and
     * always wins. It is a model catalog slug, resolved through that entry's
     * failover chain like an agent's own model; a value that matches no
     * active catalog entry is a raw model id on the agent's own provider,
     * which is how it was stored before catalog entries could be picked.
     * Without an override it's the agent's own `forAgent()` model: the one
     * model it's guaranteed to have access to.
     *
     * @return array{0: string|array<string, string>, 1: ?string}
     */
    public function forJudging(Agent $agent, ?string $judgeModel): array
    {
        if ($judgeModel === null) {
            return $this->forAgent($agent);
        }

        $chain = Cache::remember($this->cacheKey($judgeModel), self::CACHE_TTL_SECONDS, fn () => $this->resolve($judgeModel));

        $this->registerEndpoints($chain);

        return $chain !== [] ? [$chain, null] : [$agent->provider, $judgeModel];
    }

    /**
     * Whether `$provider` can actually serve requests here: it's defined in
     * `config/ai.php` (or is one of the operator's endpoints), has an API key
     * (Ollama runs locally without one), and the operator has not paused it.
     * `$ignoringPause` answers the first part only: whether it *would* run.
     */
    public static function providerIsConfigured(string $provider, bool $ignoringPause = false): bool
    {
        if (! $ignoringPause && app(ProviderPauses::class)->isPaused($provider)) {
            return false;
        }

        app(OperatorEndpoints::class)->ensureRegistered($provider);

        $config = config("ai.providers.{$provider}");

        return is_array($config) && (filled($config['key'] ?? null) || ($config['driver'] ?? null) === 'ollama');
    }

    /**
     * @return array<string, string> provider (a `Lab` value or custom
     *                               `openai-compatible` config key) => model id, ordered by priority
     */
    public function providerChain(string $catalogSlug): array
    {
        $chain = Cache::remember(
            $this->cacheKey($catalogSlug),
            self::CACHE_TTL_SECONDS,
            fn () => $this->resolve($catalogSlug),
        );

        if ($chain === []) {
            throw new RuntimeException("Model catalog entry [{$catalogSlug}] has no enabled execution route.");
        }

        $this->registerEndpoints($chain);

        return $chain;
    }

    /**
     * Invalidates the cached chain for a catalog entry: call after
     * changing its routes (enabling/disabling, reordering, adding a
     * backend) so the next resolution reflects the change immediately
     * rather than waiting out the cache TTL.
     */
    public function forget(string $catalogSlug): void
    {
        Cache::forget($this->cacheKey($catalogSlug));
    }

    /**
     * `forget()` for a model and everything whose chain runs through it: its
     * own replacement, and every model that names it as one. Call after any
     * change to the model or its routes.
     */
    public function forgetAll(ModelCatalog $model): void
    {
        $this->forget($model->slug);

        if ($model->replacement_slug !== null) {
            $this->forget($model->replacement_slug);
        }

        ModelCatalog::query()->where('replacement_slug', $model->slug)->pluck('slug')
            ->each(fn (string $slug) => $this->forget($slug));
    }

    /**
     * A chain may name one of the operator's own endpoints, which has to be
     * a registered provider before the SDK is asked to use it.
     *
     * @param  array<string, string>  $chain
     */
    private function registerEndpoints(array $chain): void
    {
        foreach (array_keys($chain) as $provider) {
            app(OperatorEndpoints::class)->ensureRegistered($provider);
        }
    }

    /**
     * The enabled routes of an active entry. A retired entry (switched off, or
     * past its `retires_at`) that names a replacement hands over to it, so
     * anything still holding the old slug (a workflow's Ask AI node, a judge
     * setting) keeps working instead of failing the day a model is retired.
     *
     * @return array<string, string>
     */
    private function resolve(string $catalogSlug, int $depth = 0): array
    {
        $catalog = ModelCatalog::query()->where('slug', $catalogSlug)->first();

        if ($catalog === null) {
            return [];
        }

        if ($catalog->hasHandedOver() && $depth < self::MAX_REPLACEMENT_HOPS) {
            return $this->resolve($catalog->replacement_slug, $depth + 1);
        }

        if (! $catalog->is_active) {
            return [];
        }

        return $catalog->routes()
            ->where('is_enabled', true)
            ->orderBy('priority')
            ->pluck('execution_model_id', 'execution_provider')
            ->all();
    }

    private function cacheKey(string $catalogSlug): string
    {
        return "model-catalog-chain:{$catalogSlug}";
    }
}
