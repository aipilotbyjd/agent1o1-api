<?php

namespace App\Services\Ai;

use App\Models\Ai\AiProviderSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * The super admin's switches for each bring-your-own-key provider, over the
 * `config/byok.php` defaults. Config says what a provider is (and so which
 * providers exist at all); a row in `ai_provider_settings` only switches
 * one on or off and changes how it is shown:
 *
 * - `byok_enabled`: customers can add and use their own key for it. Off
 *   hides it, refuses new keys, and keeps existing keys unused until it is
 *   switched back on.
 * - `connect_enabled`: one-click connect is offered (null: the config's
 *   `connect.enabled`). Never on while customer keys are off.
 * - `featured` / `sort_order`: listed first, in this order.
 * - `free_tier_note` / `disabled_message`: what customers read.
 * - `connect_client_id` / `connect_client_secret`: the one-click login app,
 *   over the config's (`.env`) values. Never cached.
 *
 * Asked on the path of every model call, so the switches are cached and the
 * cache is dropped whenever a row changes. If the table can't be read (a
 * database that is down, a deploy that hasn't migrated yet) every provider
 * keeps its config defaults, as if nothing had been switched.
 */
class AiProviderSettings
{
    public const string CACHE_KEY = 'ai-provider-settings';

    private const int CACHE_TTL_SECONDS = 300;

    /**
     * This instance's copy of the cache, so one request asks the cache once.
     *
     * @var array<string, array{byok_enabled: bool, connect_enabled: ?bool, featured: bool, sort_order: ?int, free_tier_note: ?string, disabled_message: ?string, has_connect_client: bool}>|null
     */
    private ?array $loaded = null;

    /**
     * Whether `config/byok.php` defines the provider at all.
     */
    public function isSupported(string $provider): bool
    {
        return is_array(config("byok.providers.{$provider}"));
    }

    /**
     * Whether customers can add and use their own key for the provider.
     */
    public function byokEnabled(string $provider): bool
    {
        return $this->isSupported($provider) && ($this->row($provider)['byok_enabled'] ?? true);
    }

    /**
     * Every provider customers can use their own key for, in `config/byok.php` order.
     *
     * @return list<string>
     */
    public function enabledProviders(): array
    {
        return array_values(array_filter(
            array_keys((array) config('byok.providers')),
            fn (string $provider): bool => $this->byokEnabled($provider),
        ));
    }

    /**
     * Whether one-click connect is switched on for the provider. Whether it
     * can actually run (a login app is set) is the connector's question.
     */
    public function connectEnabled(string $provider): bool
    {
        if (! $this->byokEnabled($provider) || config("byok.providers.{$provider}.connect") === null) {
            return false;
        }

        return $this->row($provider)['connect_enabled'] ?? (bool) config("byok.providers.{$provider}.connect.enabled", false);
    }

    public function freeTierNote(string $provider): ?string
    {
        return $this->row($provider)['free_tier_note'] ?? config("byok.providers.{$provider}.free_tier");
    }

    /**
     * What customers read while the provider is switched off.
     */
    public function disabledMessage(string $provider): string
    {
        return $this->row($provider)['disabled_message']
            ?? $this->label($provider).' isn\'t available right now. Keys you added are kept and used again once it is back.';
    }

    public function featured(string $provider): bool
    {
        return $this->row($provider)['featured'] ?? false;
    }

    public function sortOrder(string $provider): ?int
    {
        return $this->row($provider)['sort_order'] ?? null;
    }

    /**
     * Whether a one-click login app client id is stored here (as opposed to
     * coming from `.env`, or not being set at all).
     */
    public function hasStoredConnectClient(string $provider): bool
    {
        return $this->row($provider)['has_connect_client'] ?? false;
    }

    /**
     * The one-click login app's credentials: the ones stored by a super
     * admin, else the config's (`.env`).
     *
     * @return array{client_id: ?string, client_secret: ?string}
     */
    public function connectClient(string $provider): array
    {
        $stored = rescue(
            fn (): ?AiProviderSetting => AiProviderSetting::query()->where('provider', $provider)->first(),
            report: true,
        );

        if ($stored !== null && filled($stored->connect_client_id)) {
            return ['client_id' => $stored->connect_client_id, 'client_secret' => $stored->connect_client_secret];
        }

        return [
            'client_id' => config("byok.providers.{$provider}.connect.client_id") ?: null,
            'client_secret' => config("byok.providers.{$provider}.connect.client_secret") ?: null,
        ];
    }

    public function label(string $provider): string
    {
        return (string) config("byok.providers.{$provider}.label", Str::headline($provider));
    }

    public function forget(): void
    {
        $this->loaded = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{byok_enabled: bool, connect_enabled: ?bool, featured: bool, sort_order: ?int, free_tier_note: ?string, disabled_message: ?string, has_connect_client: bool}|null
     */
    private function row(string $provider): ?array
    {
        return $this->all()[$provider] ?? null;
    }

    /**
     * @return array<string, array{byok_enabled: bool, connect_enabled: ?bool, featured: bool, sort_order: ?int, free_tier_note: ?string, disabled_message: ?string, has_connect_client: bool}>
     */
    private function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        try {
            return $this->loaded = Cache::remember(
                self::CACHE_KEY,
                self::CACHE_TTL_SECONDS,
                fn (): array => AiProviderSetting::query()->get()->mapWithKeys(fn (AiProviderSetting $setting): array => [
                    $setting->provider => [
                        'byok_enabled' => $setting->byok_enabled,
                        'connect_enabled' => $setting->connect_enabled,
                        'featured' => $setting->featured,
                        'sort_order' => $setting->sort_order,
                        'free_tier_note' => $setting->free_tier_note,
                        'disabled_message' => $setting->disabled_message,
                        'has_connect_client' => filled($setting->connect_client_id),
                    ],
                ])->all(),
            );
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }
}
