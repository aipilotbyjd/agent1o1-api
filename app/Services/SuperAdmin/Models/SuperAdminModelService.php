<?php

namespace App\Services\SuperAdmin\Models;

use App\Models\Ai\AiEndpoint;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Ai\Operations\ModelRouteProbe;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Managing the model catalog and its routes. Every change is audited with the
 * stated reason, and drops the cached provider chain of the model (and of
 * anything that resolves through it), so it is live for the next call.
 *
 * The rules that keep a model from being left unable to run live here:
 * a route can only be switched on if the platform can run it (or a workspace
 * can bring its own key for it), a model cannot be switched on without an
 * enabled route, and the last enabled route of an active model cannot be
 * removed or switched off (switch the model off instead).
 */
class SuperAdminModelService
{
    public function __construct(
        private readonly SuperAdminAuditLogger $audit,
        private readonly ModelCatalogResolver $resolver,
        private readonly ModelRouteProbe $probe,
    ) {}

    /**
     * Every provider name a route may use: the ones `config/ai.php` defines,
     * the operator's own endpoints, and the providers a workspace can bring a
     * key for.
     *
     * @return array<int, string>
     */
    public function knownProviders(): array
    {
        return array_values(array_unique([
            ...array_keys(array_filter((array) config('ai.providers'))),
            ...AiEndpoint::query()->pluck('slug')->all(),
            ...array_keys((array) config('byok.providers')),
        ]));
    }

    /**
     * Created switched off and not internal: it goes live once it has a route.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(SuperAdmin $actor, array $data, string $reason): ModelCatalog
    {
        return DB::transaction(function () use ($actor, $data, $reason): ModelCatalog {
            $model = ModelCatalog::query()->create([
                ...$data,
                'is_active' => false,
                'is_internal' => false,
                'sort_order' => $data['sort_order'] ?? ((int) ModelCatalog::query()->max('sort_order')) + 1,
            ]);

            $this->audit->record($actor, 'models.created', $model, null, [
                'slug' => $model->slug,
                'display_name' => $model->display_name,
                'brand' => $model->brand,
            ], $reason);

            return $model;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SuperAdmin $actor, ModelCatalog $model, array $data, string $reason): ModelCatalog
    {
        $turningOn = ($data['is_active'] ?? $model->is_active) && ! $model->is_active;
        $turningOff = array_key_exists('is_active', $data) && ! $data['is_active'] && $model->is_active;

        if ($turningOn && ! $model->routes()->where('is_enabled', true)->exists()) {
            throw $this->conflict('This model has no enabled route, so it cannot be switched on. Enable a route first.');
        }

        if ($turningOff && $model->is_internal) {
            throw $this->conflict('An internal model is used by the platform itself and cannot be switched off. Retire it with a replacement instead.');
        }

        $original = $model->getAttributes();

        DB::transaction(function () use ($actor, $model, $data, $reason, $original): void {
            $model->fill($data)->save();

            $this->audit->recordChanges($actor, 'models.updated', $model, $original, $reason);
        });

        $this->resolver->forgetAll($model);

        return $model;
    }

    /**
     * @param  array{execution_provider: string, execution_model_id: string, priority?: int, is_enabled?: bool}  $data
     */
    public function addRoute(SuperAdmin $actor, ModelCatalog $model, array $data, string $reason): ModelRoute
    {
        $exists = $model->routes()
            ->where('execution_provider', $data['execution_provider'])
            ->where('execution_model_id', $data['execution_model_id'])
            ->exists();

        if ($exists) {
            throw $this->conflict('This model already has that route.');
        }

        $enabled = (bool) ($data['is_enabled'] ?? false);

        if ($enabled) {
            $this->assertRunnable($data['execution_provider']);
        }

        $route = DB::transaction(function () use ($actor, $model, $data, $enabled, $reason): ModelRoute {
            $route = $model->routes()->create([
                'execution_provider' => $data['execution_provider'],
                'execution_model_id' => $data['execution_model_id'],
                'priority' => $data['priority'] ?? ((int) $model->routes()->max('priority')) + 1,
                'is_enabled' => $enabled,
            ]);

            $this->audit->record($actor, 'models.route.added', $model, null, [
                'route_id' => $route->id,
                'provider' => $route->execution_provider,
                'model_id' => $route->execution_model_id,
                'priority' => $route->priority,
                'is_enabled' => $route->is_enabled,
            ], $reason);

            return $route;
        });

        $this->resolver->forgetAll($model);

        return $route;
    }

    /**
     * @param  array{priority?: int, is_enabled?: bool, options?: array<string, mixed>|null}  $data
     */
    public function updateRoute(SuperAdmin $actor, ModelCatalog $model, ModelRoute $route, array $data, string $reason): ModelRoute
    {
        $this->assertBelongs($model, $route);

        if (($data['is_enabled'] ?? false) && ! $route->is_enabled) {
            $this->assertRunnable($route->execution_provider);
        }

        if (array_key_exists('is_enabled', $data) && ! $data['is_enabled'] && $route->is_enabled) {
            $this->assertNotLastEnabled($model, $route, 'switch off');
        }

        $original = $route->getAttributes();

        DB::transaction(function () use ($actor, $route, $data, $reason, $original): void {
            $route->fill($data)->save();

            $this->audit->recordChanges($actor, 'models.route.updated', $route, $original, $reason);
        });

        $this->resolver->forgetAll($model);

        return $route;
    }

    public function removeRoute(SuperAdmin $actor, ModelCatalog $model, ModelRoute $route, string $reason): void
    {
        $this->assertBelongs($model, $route);

        if ($route->is_enabled) {
            $this->assertNotLastEnabled($model, $route, 'remove');
        }

        DB::transaction(function () use ($actor, $model, $route, $reason): void {
            $this->audit->record($actor, 'models.route.removed', $model, [
                'route_id' => $route->id,
                'provider' => $route->execution_provider,
                'model_id' => $route->execution_model_id,
                'priority' => $route->priority,
                'is_enabled' => $route->is_enabled,
            ], null, $reason);

            $route->delete();
        });

        $this->resolver->forgetAll($model);
    }

    /**
     * Puts the routes in the given order: the first is tried first. The list
     * must name every route of the model exactly once.
     *
     * @param  array<int, string>  $routeIds
     */
    public function reorder(SuperAdmin $actor, ModelCatalog $model, array $routeIds, string $reason): ModelCatalog
    {
        $current = $model->routes()->orderBy('priority')->pluck('id')->all();

        if (count($routeIds) !== count($current) || array_diff($routeIds, $current) !== []) {
            throw ValidationException::withMessages(['route_ids' => 'List every route of this model exactly once.']);
        }

        DB::transaction(function () use ($actor, $model, $routeIds, $current, $reason): void {
            foreach (array_values($routeIds) as $position => $id) {
                ModelRoute::query()->whereKey($id)->update(['priority' => $position]);
            }

            $this->audit->record($actor, 'models.routes.reordered', $model, ['route_ids' => $current], ['route_ids' => array_values($routeIds)], $reason);
        });

        $this->resolver->forgetAll($model);

        return $model;
    }

    /**
     * Sends the route one tiny real prompt and keeps the outcome on it. Costs
     * a fraction of a cent; always on the platform's own key.
     *
     * @return array{ok: bool, latency_ms: ?int, prompt_tokens: ?int, completion_tokens: ?int, estimated_cost_usd: ?float, message: string}
     */
    public function testRoute(SuperAdmin $actor, ModelCatalog $model, ModelRoute $route): array
    {
        $this->assertBelongs($model, $route);

        $result = $this->probe->probe($route->execution_provider, $route->execution_model_id);

        $route->forceFill([
            'last_checked_at' => now(),
            'last_check_ok' => $result['ok'],
            'last_check_latency_ms' => $result['latency_ms'],
            'last_check_message' => $result['message'],
        ])->save();

        $this->audit->record($actor, 'models.route.tested', $model, null, [
            'route_id' => $route->id,
            'provider' => $route->execution_provider,
            'model_id' => $route->execution_model_id,
            'ok' => $result['ok'],
            'latency_ms' => $result['latency_ms'],
        ]);

        return $result;
    }

    /**
     * A route can only be switched on if the platform can run it, or the
     * provider is one a workspace can bring its own key for (a route that
     * only workspaces with a key will use).
     */
    private function assertRunnable(string $provider): void
    {
        if (! ModelCatalogResolver::providerIsConfigured($provider) && config("byok.providers.{$provider}") === null) {
            throw $this->conflict("The platform has no key for [{$provider}], so a route on it cannot be switched on. Add the key, or add it as an endpoint.");
        }
    }

    private function assertNotLastEnabled(ModelCatalog $model, ModelRoute $route, string $verb): void
    {
        $otherEnabled = $model->routes()->where('is_enabled', true)->whereKeyNot($route->id)->exists();

        if ($model->is_active && ! $otherEnabled) {
            throw $this->conflict("This is the only enabled route of an active model, so you cannot {$verb} it. Switch the model off first, or add another route.");
        }
    }

    private function assertBelongs(ModelCatalog $model, ModelRoute $route): void
    {
        abort_unless($route->model_catalog_id === $model->id, 404);
    }

    private function conflict(string $message): HttpException
    {
        return new HttpException(409, $message);
    }
}
