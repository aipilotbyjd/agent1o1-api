<?php

namespace App\Services\Billing;

use App\Enums\Billing\BillingInterval;
use App\Models\Billing\BillingSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Cached reads and writes of the admin-editable billing switches. A value in
 * `billing_settings` wins over the same key in `config/billing.php`, which
 * stays as the fallback so an install that never touches the admin keeps its
 * env-driven behaviour.
 */
class BillingSettings
{
    private const string CACHE_KEY = 'billing:settings';

    /**
     * The credit-policy and guardrail settings an admin may change without a
     * deploy. Each falls back to the same key under `config/billing.php`.
     *
     * @var list<string>
     */
    public const array POLICY_KEYS = [
        'free_credits.one_per_owner',
        'free_credits.require_verified_email',
        'trials.one_per_user',
        'trials.one_per_card',
        'trials.block_prepaid_cards',
        'abuse.enforcement',
        'abuse.max_free_claims_per_network_per_day',
        'abuse.block_disposable_email',
        'guardrails.free_tier_daily_budget_usd',
        'guardrails.enabled',
    ];

    /**
     * @return array<string, mixed>
     */
    private function all(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => BillingSetting::query()->pluck('value', 'key')->all(),
        );
    }

    public function intervalEnabled(BillingInterval $interval): bool
    {
        $override = $this->all()["intervals.{$interval->value}"] ?? null;

        if ($override !== null) {
            return (bool) $override;
        }

        return (bool) config("billing.intervals.{$interval->value}", true);
    }

    /**
     * @return array<string, bool>
     */
    public function intervals(): array
    {
        $states = [];

        foreach (BillingInterval::cases() as $interval) {
            $states[$interval->value] = $this->intervalEnabled($interval);
        }

        return $states;
    }

    public function setIntervalEnabled(BillingInterval $interval, bool $enabled): void
    {
        BillingSetting::query()->updateOrCreate(['key' => "intervals.{$interval->value}"], ['value' => $enabled]);

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * A credit-policy or guardrail setting: the admin's value from
     * `billing_settings` when one is stored, else `config("billing.$key")`.
     * Keys are listed in `self::POLICY_KEYS` so an admin cannot invent one.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $override = $this->all()[$key] ?? null;

        if ($override !== null) {
            return $override;
        }

        return config("billing.{$key}", $default);
    }

    public function set(string $key, mixed $value): void
    {
        if (! in_array($key, self::POLICY_KEYS, true)) {
            throw new \InvalidArgumentException("Unknown billing setting [{$key}].");
        }

        BillingSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    public function policies(): array
    {
        return collect(self::POLICY_KEYS)->mapWithKeys(fn (string $key): array => [$key => $this->get($key)])->all();
    }
}
