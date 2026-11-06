<?php

namespace App\Services\Ai;

use App\Ai\Observability\AiCallContext;
use App\Enums\Ai\AiProviderCredentialStatus;
use App\Enums\Ai\PlatformKeyUsage;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Exceptions\ModelBusyException;
use App\Exceptions\ModelPriceUnverifiedException;
use App\Exceptions\OwnAiKeyRequiredException;
use App\Exceptions\ProviderPausedException;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\WorkspaceAiKeyPolicy;
use App\Services\Ai\Connect\AiProviderConnectors;
use App\Services\Ai\Connect\AiProviderTokenRefresher;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
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
 * `OwnAiKeyRequiredException`). A `personal` call (a member's own assistant,
 * which reads their mail, inbox and calendar) skips the team's keys unless
 * the policy's `team_keys_cover_personal_assistants` allows them: whoever
 * owns a team key's provider account may be able to see what is sent on it.
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

    /**
     * The only parts of the platform's provider config a workspace key is
     * built with: where and how to call, never with what. Anything else
     * (the platform's key, AWS credentials, custom headers) stays out, so a
     * workspace's call can never run on, or carry, a platform credential.
     * `store` is not shared either: see `register()`.
     *
     * @var array<int, string>
     */
    private const array SHARED_CONFIG_KEYS = ['driver', 'url', 'api_version', 'deployment', 'embedding_deployment', 'image_deployment', 'models', 'region'];

    public function __construct(private readonly AiManager $ai) {}

    /**
     * `$provider`/`$model` exactly as `ModelCatalogResolver` (or an agent's
     * own columns) hand them over; the result is the same shape, ready for
     * `->prompt(provider:, model:)`. `$personal` marks a call made for one
     * member's private data, which runs on the team's keys only when the
     * workspace's policy says they cover personal assistants.
     *
     * @param  string|array<string, string>|null  $provider
     * @return array{0: string|array<string, string>|null, 1: ?string}
     */
    public function apply(string|array|null $provider, ?string $model, string $workspaceId, ?string $userId, bool $personal = false): array
    {
        // Every model call passes through here first, so this is where the
        // call ledger learns who is paying when the caller did not say.
        app(AiCallContext::class)->remember($workspaceId, $userId);

        if ($provider === null || $provider === []) {
            return [$provider, $model];
        }

        $chain = is_array($provider) ? $provider : [$provider => $model];
        $policy = WorkspaceAiKeyPolicy::forWorkspace($workspaceId);
        $includeTeamKeys = ! $personal || $policy->team_keys_cover_personal_assistants;
        $credentials = $this->usable($this->candidates($workspaceId, $policy->allow_personal_keys ? $userId : null, array_keys($chain), $includeTeamKeys));
        $platformAllowed = match ($policy->platform_usage) {
            PlatformKeyUsage::Fallback => true,
            PlatformKeyUsage::WhenNoKey => $credentials->isEmpty(),
            PlatformKeyUsage::Never => false,
        };
        $pauses = app(ProviderPauses::class);
        $limiter = app(EndpointRateLimiter::class);
        $priceGate = app(PriceGate::class);

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
        $unpricedHops = 0;

        foreach ($chain as $hop => $hopModel) {
            if ($credentials->has($hop)) {
                $result[$this->register($credentials[$hop], $hop)] = $hopModel;
            }

            if ($platformAllowed && ModelCatalogResolver::providerIsConfigured($hop) && ! $priceGate->allowsPlatformCall($hop, $hopModel)) {
                // `billing.price_enforcement` is `enforce` and this model's
                // price is not verified: never bill a customer on a guess.
                $unpricedHops++;

                continue;
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

        if ($result === [] && $unpricedHops > 0) {
            throw ModelPriceUnverifiedException::forProviders();
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
     * The platform provider a `byok-{credential id}` name stands in for
     * (`openai`, `anthropic`, ...), so a call served by a workspace's own key
     * can still be priced at what it would have cost on the platform's key.
     * Null when the name is not a BYOK name or the key no longer exists.
     */
    public static function underlyingProvider(mixed $provider): ?string
    {
        if (! self::isByok($provider)) {
            return null;
        }

        $credentialId = substr((string) $provider, strlen(self::PREFIX));

        return once(fn (): ?string => AiProviderCredential::query()->whereKey($credentialId)->value('execution_provider'));
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

        return $this->credentialsFor($workspaceId, $userId, app(AiProviderSettings::class)->enabledProviders())->keys()->all();
    }

    /**
     * @param  array<string, string|null>  $chain
     */
    private function ownKeyRequired(array $chain): OwnAiKeyRequiredException
    {
        return OwnAiKeyRequiredException::forProviders(array_values(array_map(
            fn (string $hop): string => (string) config("byok.providers.{$hop}.label"),
            array_filter(array_keys($chain), fn (string $hop): bool => app(AiProviderSettings::class)->byokEnabled($hop)),
        )));
    }

    /**
     * The first usable key per provider, in preference order. A one-click
     * connection whose token is about to run out is renewed before it is put
     * in front of a call; one that can't be renewed is passed over (and out
     * of rotation until reconnected when the provider refused), so the next
     * key for that provider is used instead.
     *
     * @param  Collection<int, AiProviderCredential>  $candidates
     * @return Collection<string, AiProviderCredential>
     */
    private function usable(Collection $candidates): Collection
    {
        $picked = collect();

        foreach ($candidates as $credential) {
            if ($picked->has($credential->execution_provider)) {
                continue;
            }

            if (! $credential->tokenExpiresWithin(60) || app(AiProviderTokenRefresher::class)->refresh($credential)) {
                $picked->put($credential->execution_provider, $credential);
            }
        }

        return $picked;
    }

    /**
     * The one usable key per provider: the member's personal key before the
     * team's, a group's default before its other keys, oldest first. With
     * `$includeTeamKeys` off only the member's personal keys count.
     *
     * @param  array<int, string>  $providers
     * @return Collection<string, AiProviderCredential>
     */
    private function credentialsFor(string $workspaceId, ?string $userId, array $providers, bool $includeTeamKeys = true): Collection
    {
        return $this->candidates($workspaceId, $userId, $providers, $includeTeamKeys)
            ->unique('execution_provider')
            ->keyBy('execution_provider');
    }

    /**
     * Every working key the member's calls could use for these providers, in
     * preference order: the member's personal keys before the team's, a
     * group's default before its other keys, oldest first. With
     * `$includeTeamKeys` off only the member's personal keys count.
     *
     * @param  array<int, string>  $providers
     * @return Collection<int, AiProviderCredential>
     */
    private function candidates(string $workspaceId, ?string $userId, array $providers, bool $includeTeamKeys = true): Collection
    {
        // A provider a super admin has switched off keeps its keys, unused.
        $providers = array_values(array_intersect($providers, app(AiProviderSettings::class)->enabledProviders()));

        if ($providers === [] || (! $includeTeamKeys && $userId === null)) {
            return collect();
        }

        return AiProviderCredential::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('execution_provider', $providers)
            ->where('validation_status', AiProviderCredentialStatus::Valid->value)
            ->where(fn ($query) => $query
                ->when($includeTeamKeys, fn ($query) => $query->where('scope', ConnectorCredentialScope::Team->value))
                ->when($userId !== null, fn ($query) => $query->orWhere(fn ($query) => $query
                    ->where('scope', ConnectorCredentialScope::Personal->value)
                    ->where('created_by', $userId))))
            ->get()
            ->sortBy(fn (AiProviderCredential $credential): array => [
                $credential->scope === ConnectorCredentialScope::Personal ? 0 : 1,
                $credential->is_default ? 0 : 1,
                $credential->created_at?->getTimestamp() ?? 0,
            ])
            ->values();
    }

    /**
     * Builds the key as an on-demand SDK provider (`AiManager::build()`) and
     * returns its name. It never enters `config('ai.providers')`, so it does
     * not show up in provider lists, and the SDK forgets it between queue
     * jobs, so a worker never keeps calling with a key that has since been
     * replaced or reuses one workspace's key for another's job. Building it
     * again replaces the instance with the current key. Only the platform
     * config's `SHARED_CONFIG_KEYS` are carried over.
     *
     * A workspace key is always built with `store` off. Whoever owns the key
     * can read whatever their provider keeps for it (OpenAI shows stored
     * responses in the key owner's dashboard), and a stored call would hand
     * them the platform's system prompts, tool definitions and context. With
     * `store` off the SDK sends the full history on every call instead.
     * This narrows what the provider keeps; it does not hide the request
     * from the key's owner, so no prompt may ever carry a platform secret.
     */
    private function register(AiProviderCredential $credential, string $provider): string
    {
        $name = self::PREFIX.$credential->id;

        $this->ai->build([
            'driver' => $provider,
            ...Arr::only((array) config("ai.providers.{$provider}", []), self::SHARED_CONFIG_KEYS),
            // e.g. Sign in with ChatGPT calls must not be stored by OpenAI.
            ...($credential->isOAuth() ? (app(AiProviderConnectors::class)->for($provider)?->providerOverrides() ?? []) : []),
            'store' => false,
            'name' => $name,
            'key' => $credential->apiKey(),
        ]);

        if ($credential->last_used_at === null || $credential->last_used_at->diffInSeconds(now()) > self::LAST_USED_RESOLUTION_SECONDS) {
            AiProviderCredential::query()->whereKey($credential->id)->update(['last_used_at' => now()]);
        }

        return $name;
    }
}
