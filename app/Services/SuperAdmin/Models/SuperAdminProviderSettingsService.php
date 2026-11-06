<?php

namespace App\Services\SuperAdmin\Models;

use App\Enums\Ai\AiProviderCredentialStatus;
use App\Jobs\Ai\NotifyAiProviderAvailabilityJob;
use App\Models\Agents\Agent;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\AiProviderSetting;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\Ai\AiProviderSettings;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\SuperAdmin\Alerts\SuperAdminAlerter;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The super admin's switches for each bring-your-own-key provider (see
 * `AiProviderSettings`): whether customers can use their own key for it,
 * one-click connect, how it is shown, and the one-click login app. Every
 * change is audited with its reason. Turning customer keys off or back on
 * also raises an alert, and can tell the workspaces that have a key for it
 * (in-app, or in-app and email: the super admin's choice each time).
 */
class SuperAdminProviderSettingsService
{
    /** Who to tell when customer keys are switched: nobody, in-app, or in-app and email. */
    public const array NOTIFY_OPTIONS = ['none', 'in_app', 'email'];

    /** The fields `update()` takes, besides the reason and who to tell. */
    public const array FIELDS = ['byok_enabled', 'connect_enabled', 'featured', 'sort_order', 'free_tier_note', 'disabled_message'];

    public function __construct(
        private readonly AiProviderSettings $settings,
        private readonly SuperAdminAuditLogger $audit,
        private readonly SuperAdminAlerter $alerter,
    ) {}

    /**
     * @param  array<string, mixed>  $data  any of `FIELDS`
     */
    public function update(SuperAdmin $actor, string $provider, array $data, string $reason, string $notify = 'none'): AiProviderSetting
    {
        $this->assertSupported($provider);

        if (($data['connect_enabled'] ?? null) === true && config("byok.providers.{$provider}.connect") === null) {
            throw new HttpException(422, $this->settings->label($provider).' has no one-click connect.');
        }

        $setting = AiProviderSetting::query()->firstOrNew(['provider' => $provider]);
        $wasEnabled = $this->settings->byokEnabled($provider);
        $before = $this->snapshot($provider);

        DB::transaction(function () use ($actor, $setting, $data, $reason, $provider, $before): void {
            $setting->fill([...array_intersect_key($data, array_flip(self::FIELDS)), 'updated_by' => $actor->id])->save();

            $this->audit->record($actor, 'models.provider.updated', $setting, $before, $this->snapshot($provider), $reason);
        });

        $isEnabled = $this->settings->byokEnabled($provider);

        if ($wasEnabled !== $isEnabled) {
            $this->alerter->raise(
                'provider_byok_switched',
                $isEnabled ? "Customer keys for [{$provider}] were turned back on: {$reason}" : "Customer keys for [{$provider}] were turned off: {$reason}",
                ['by' => $actor->email, 'notified' => $notify],
            );

            if ($notify !== 'none') {
                NotifyAiProviderAvailabilityJob::dispatch(
                    $provider,
                    $isEnabled,
                    $notify === 'email',
                    $isEnabled ? null : $this->settings->disabledMessage($provider),
                );
            }
        }

        return $setting->refresh();
    }

    /**
     * What turning customer keys for the provider off would touch: the
     * workspaces and keys that have it, and the agents whose model can run
     * on nothing else (no other route the platform or the workspace serves).
     *
     * @return array{provider: string, workspaces: int, keys: array{total: int, valid: int}, models_only_on_provider: list<array{id: string, display_name: string}>, agents: array{total: int, sample: list<array{id: string, name: string, workspace_id: string}>}}
     */
    public function impact(string $provider): array
    {
        $this->assertSupported($provider);

        $keys = AiProviderCredential::query()->where('execution_provider', $provider);
        $workspaceIds = (clone $keys)->distinct()->pluck('workspace_id');

        $modelsOnlyHere = ModelCatalog::query()
            ->whereHas('routes', fn ($query) => $query->where('execution_provider', $provider)->where('is_enabled', true))
            ->with(['routes' => fn ($query) => $query->where('is_enabled', true)])
            ->get()
            ->reject(fn (ModelCatalog $model): bool => $model->routes->contains(fn (ModelRoute $route): bool => $route->execution_provider !== $provider
                && (ModelCatalogResolver::providerIsConfigured($route->execution_provider) || $this->settings->byokEnabled($route->execution_provider))))
            ->values();

        $agents = Agent::query()
            ->whereIn('workspace_id', $workspaceIds)
            ->whereIn('model_catalog_id', $modelsOnlyHere->pluck('id'));

        return [
            'provider' => $provider,
            'workspaces' => $workspaceIds->count(),
            'keys' => [
                'total' => (clone $keys)->count(),
                'valid' => (clone $keys)->where('validation_status', AiProviderCredentialStatus::Valid->value)->count(),
            ],
            'models_only_on_provider' => $modelsOnlyHere->map(fn (ModelCatalog $model): array => ['id' => $model->id, 'display_name' => $model->display_name])->all(),
            'agents' => [
                'total' => (clone $agents)->count(),
                'sample' => (clone $agents)->orderBy('name')->limit(10)->get(['id', 'name', 'workspace_id'])
                    ->map(fn (Agent $agent): array => ['id' => $agent->id, 'name' => $agent->name, 'workspace_id' => $agent->workspace_id])
                    ->all(),
            ],
        ];
    }

    /**
     * Stores the one-click login app for an OAuth provider (Hugging Face,
     * ChatGPT), over the `.env` values. Neither value is ever returned.
     */
    public function setConnectCredentials(SuperAdmin $actor, string $provider, string $clientId, ?string $clientSecret, string $reason): void
    {
        $this->assertSupported($provider);

        if (config("byok.providers.{$provider}.connect.driver") !== 'oauth') {
            throw new HttpException(422, $this->settings->label($provider).' needs no login app.');
        }

        $setting = AiProviderSetting::query()->firstOrNew(['provider' => $provider]);
        $hadStored = filled($setting->connect_client_id);

        DB::transaction(function () use ($actor, $setting, $clientId, $clientSecret, $reason, $provider, $hadStored): void {
            $setting->fill([
                'connect_client_id' => trim($clientId),
                'connect_client_secret' => filled($clientSecret) ? trim($clientSecret) : null,
                'updated_by' => $actor->id,
            ])->save();

            $this->audit->record($actor, 'models.provider.connect_credentials_set', $setting, ['provider' => $provider, 'stored' => $hadStored], [
                'provider' => $provider,
                'client_id_hint' => Str::mask(trim($clientId), '*', 4, max(0, strlen(trim($clientId)) - 8)),
                'has_secret' => filled($clientSecret),
            ], $reason);
        });
    }

    /**
     * Forgets the stored login app, so the `.env` values (if any) are used again.
     */
    public function clearConnectCredentials(SuperAdmin $actor, string $provider, string $reason): void
    {
        $this->assertSupported($provider);

        $setting = AiProviderSetting::query()->where('provider', $provider)->first();

        if ($setting === null || blank($setting->connect_client_id)) {
            throw new HttpException(409, "No login app is stored for [{$provider}].");
        }

        DB::transaction(function () use ($actor, $setting, $reason, $provider): void {
            $setting->fill(['connect_client_id' => null, 'connect_client_secret' => null, 'updated_by' => $actor->id])->save();

            $this->audit->record($actor, 'models.provider.connect_credentials_cleared', $setting, ['provider' => $provider], null, $reason);
        });
    }

    /**
     * The switches as they stand, for the audit log: never the login app's values.
     *
     * @return array<string, mixed>
     */
    private function snapshot(string $provider): array
    {
        return [
            'provider' => $provider,
            'byok_enabled' => $this->settings->byokEnabled($provider),
            'connect_enabled' => $this->settings->connectEnabled($provider),
            'featured' => $this->settings->featured($provider),
            'sort_order' => $this->settings->sortOrder($provider),
            'free_tier_note' => $this->settings->freeTierNote($provider),
            'disabled_message' => AiProviderSetting::query()->where('provider', $provider)->value('disabled_message'),
        ];
    }

    private function assertSupported(string $provider): void
    {
        if (! $this->settings->isSupported($provider)) {
            throw new HttpException(404, "Customers can't bring a key for [{$provider}].");
        }
    }
}
