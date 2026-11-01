<?php

namespace App\Services\Ai;

use App\Enums\Ai\AiProviderCredentialStatus;
use App\Enums\Ai\PlatformKeyUsage;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Exceptions\ModelBusyException;
use App\Exceptions\OwnAiKeyRequiredException;
use App\Exceptions\ProviderPausedException;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\WorkspaceAiKeyPolicy;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Laravel\Ai\AiManager;
use Throwable;

/**
 * Bring your own key, at call time. `ModelCatalogResolver` stays global:
 * it answers "which backends serve this model", and this answers "whose
 * key pays for each of them" for one workspace and one acting member.
 *
 * For every hop of a provider chain whose provider has a usable key here
 * (the member's own personal key first, then the workspace's team key),
 * the key is registered as a runtime provider (`byok-{credential id}`, the
 * same driver and URL as the static entry it stands in for) and put just
 * ahead of that hop:
 *
 *     ['openai' => 'gpt-4o']  →  ['byok-9f…' => 'gpt-4o', 'openai' => 'gpt-4o']
 *
 * The platform's own hop stays behind it (when the platform has a key for
 * it), so a failover-worthy error on the workspace's key (rate limited,
 * out of quota) still lands on a working backend. A hop nobody has a key
 * for (no platform key, no workspace key) is left out, so a route can be
 * enabled for a provider the platform doesn't pay for and only workspaces
 * that brought a key for it will use it; everyone else goes straight to
 * the next hop instead of failing on a keyless call. A rejected key is not
 * failover-worthy to the SDK; the scheduled re-check takes such a key out
 * of rotation instead (`CheckAiProviderCredentialJob`).
 *
 * The workspace's `WorkspaceAiKeyPolicy` can narrow this: personal keys
 * switched off are skipped, and the platform's hops are dropped once the
 * workspace has a key for the call (`WhenNoKey`) or always (`Never`: a
 * call nothing of the workspace's covers then throws
 * `OwnAiKeyRequiredException`).
 *
 * The SDK reports the serving provider's config name back on every
 * response, so `isByok()` on a usage record says whose key ran the call,
 * which is how `CreditMeter` knows not to bill its tokens.
 */
class ByokProviderRegistrar
{
    public const string PREFIX = 'byok-';

    /**
     * How stale `last_used_at` may get before a call refreshes it: one
     * write per key per few minutes rather than one per call.
     */
    private const int LAST_USED_RESOLUTION_SECONDS = 300;

    public function __construct(private readonly AiManager $ai) {}

    /**
     * `$provider`/`$model` exactly as `ModelCatalogResolver` (or an agent's
     * own columns) hand them over; the result is the same shape, ready for
     * `->prompt(provider:, model:)`.
     *
     * @param  string|array<string, string>|null  $provider
     * @return array{0: string|array<string, string>|null, 1: ?string}
     */
    public function apply(string|array|null $provider, ?string $model, string $workspaceId, ?string $userId): array
    {
        if ($provider === null || $provider === []) {
            return [$provider, $model];
        }

        $chain = is_array($provider) ? $provider : [$provider => $model];
        $policy = WorkspaceAiKeyPolicy::forWorkspace($workspaceId);
        $credentials = $this->credentialsFor($workspaceId, $policy->allow_personal_keys ? $userId : null, array_keys($chain));
        $platformAllowed = match ($policy->platform_usage) {
            PlatformKeyUsage::Fallback => true,
            PlatformKeyUsage::WhenNoKey => $credentials->isEmpty(),
            PlatformKeyUsage::Never => false,
        };
        $pauses = app(ProviderPauses::class);
        $limiter = app(EndpointRateLimiter::class);

        // A bare provider with no model leaves the model to the driver's
        // default, which only a single-provider call can express.
        if (! is_array($provider) && $model === null) {
            return match (true) {
                $credentials->has($provider) => [$this->register($credentials[$provider], $provider), null],
                ! $platformAllowed => throw $this->ownKeyRequired($chain),
                $pauses->isPaused($provider) => throw ProviderPausedException::forProviders(),
                $platformAllowed && ModelCatalogResolver::providerIsConfigured($provider)
                    && ($wait = $limiter->attempt($provider, $workspaceId)) !== null => throw ModelBusyException::retryIn($wait),
                default => [$provider, $model],
            };
        }

        $result = [];
        $busyFor = 0;

        foreach ($chain as $hop => $hopModel) {
            if ($credentials->has($hop)) {
                $result[$this->register($credentials[$hop], $hop)] = $hopModel;
            }

            if ($platformAllowed && ModelCatalogResolver::providerIsConfigured($hop)) {
                // A hop at its request limit is skipped, so the call fails over to the next one.
                $wait = $limiter->attempt($hop, $workspaceId);

                if ($wait === null) {
                    $result[$hop] = $hopModel;
                } else {
                    $busyFor = max($busyFor, $wait);
                }
            }
        }

        if ($result === []) {
            // Nothing here has a key at all: hand the chain over untouched, so
            // the call fails the way it always has rather than with no provider.
            // Unless the reason is that the operator paused them: then the call
            // must not run on the paused key by way of this fallback.
            if ($platformAllowed) {
                if ($busyFor > 0) {
                    throw ModelBusyException::retryIn($busyFor);
                }

                if (array_filter(array_keys($chain), fn (string $hop): bool => $pauses->isPaused($hop)) !== []) {
                    throw ProviderPausedException::forProviders();
                }

                return [$provider, $model];
            }

            throw $this->ownKeyRequired($chain);
        }

        return $result === $chain && ! is_array($provider) ? [$provider, $model] : [$result, null];
    }

    /**
     * The embeddings provider for `Embeddings::for()->generate()`, on the
     * workspace's own key where it has one for the platform's embeddings
     * provider. Never a different provider or model: stored vectors are
     * only comparable with ones from the same model, so only whose account
     * pays changes: the model, read from the same provider config, doesn't.
     *
     * @return string|array<string, null>
     */
    public function embeddingsProvider(string $workspaceId, ?string $userId): string|array
    {
        $platform = (string) config('ai.default_for_embeddings');

        [$chain] = $this->apply([$platform => null], null, $workspaceId, $userId);

        return $chain === [$platform => null] ? $platform : $chain;
    }

    /**
     * When a call led by a workspace key failed because the provider refused
     * the key (401/403: revoked, expired), re-checks that key straight away
     * so it's marked rejected and stops being used, instead of failing calls
     * until the scheduled re-check. Returns the key when it was to blame.
     *
     * @param  string|array<string, string|null>|null  $provider  the chain the call was made with
     */
    public function rejectedKey(Throwable $exception, string|array|null $provider): ?AiProviderCredential
    {
        $status = $exception instanceof RequestException ? $exception->response?->status() : null;
        $lead = is_array($provider) ? array_key_first($provider) : $provider;

        if (! in_array($status, [401, 403], true) || ! self::isByok($lead)) {
            return null;
        }

        $credential = AiProviderCredential::query()->find(substr((string) $lead, strlen(self::PREFIX)));

        if ($credential !== null) {
            app(AiProviderKeyChecker::class)->validate($credential);
        }

        return $credential;
    }

    /**
     * Whether a provider name (as the SDK reports it on a response's meta,
     * and so on every usage record) is a workspace's own key.
     */
    public static function isByok(mixed $provider): bool
    {
        return is_string($provider) && str_starts_with($provider, self::PREFIX);
    }

    /**
     * Which providers this member's calls would run on their workspace's own
     * key, for marking catalog models usable that the platform can't serve.
     *
     * @return array<int, string>
     */
    public function coveredProviders(string $workspaceId, ?string $userId): array
    {
        $userId = WorkspaceAiKeyPolicy::forWorkspace($workspaceId)->allow_personal_keys ? $userId : null;

        return $this->credentialsFor($workspaceId, $userId, array_keys((array) config('byok.providers')))->keys()->all();
    }

    /**
     * @param  array<string, string|null>  $chain
     */
    private function ownKeyRequired(array $chain): OwnAiKeyRequiredException
    {
        return OwnAiKeyRequiredException::forProviders(array_values(array_map(
            fn (string $hop): string => (string) config("byok.providers.{$hop}.label"),
            array_filter(array_keys($chain), fn (string $hop): bool => config("byok.providers.{$hop}") !== null),
        )));
    }

    /**
     * The one usable key per provider: the member's personal key before the
     * team's, a group's default before its other keys, oldest first.
     *
     * @param  array<int, string>  $providers
     * @return Collection<string, AiProviderCredential>
     */
    private function credentialsFor(string $workspaceId, ?string $userId, array $providers): Collection
    {
        $providers = array_values(array_intersect($providers, array_keys((array) config('byok.providers'))));

        if ($providers === []) {
            return collect();
        }

        return AiProviderCredential::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('execution_provider', $providers)
            ->where('validation_status', AiProviderCredentialStatus::Valid->value)
            ->where(fn ($query) => $query
                ->where('scope', ConnectorCredentialScope::Team->value)
                ->when($userId !== null, fn ($query) => $query->orWhere(fn ($query) => $query
                    ->where('scope', ConnectorCredentialScope::Personal->value)
                    ->where('created_by', $userId))))
            ->get()
            ->sortBy(fn (AiProviderCredential $credential): array => [
                $credential->scope === ConnectorCredentialScope::Personal ? 0 : 1,
                $credential->is_default ? 0 : 1,
                $credential->created_at?->getTimestamp() ?? 0,
            ])
            ->unique('execution_provider')
            ->keyBy('execution_provider');
    }

    /**
     * Registers the key as a runtime provider and returns its name. The
     * cached SDK instance is dropped every time, so a long-running worker
     * never keeps calling with a key that has since been replaced.
     */
    private function register(AiProviderCredential $credential, string $provider): string
    {
        $name = self::PREFIX.$credential->id;

        config(["ai.providers.{$name}" => [
            ...(array) config("ai.providers.{$provider}", ['driver' => $provider]),
            'key' => $credential->apiKey(),
        ]]);

        $this->ai->purge($name);

        if ($credential->last_used_at === null || $credential->last_used_at->diffInSeconds(now()) > self::LAST_USED_RESOLUTION_SECONDS) {
            AiProviderCredential::query()->whereKey($credential->id)->update(['last_used_at' => now()]);
        }

        return $name;
    }
}
