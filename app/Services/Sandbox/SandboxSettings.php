<?php

namespace App\Services\Sandbox;

use App\Models\Sandbox\SandboxSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Cached reads and writes of the admin-editable sandbox switches: the kill
 * switch (stops all code running at once) and the monthly budget (stops it
 * when the month's estimated provider cost reaches the limit).
 */
class SandboxSettings
{
    private const string CACHE_KEY = 'sandbox:settings';

    /**
     * @return array<string, mixed>
     */
    private function all(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => SandboxSetting::query()->pluck('value', 'key')->all(),
        );
    }

    public function killed(): bool
    {
        return (bool) ($this->all()['kill_switch'] ?? false);
    }

    /**
     * The most the month's runs may cost the operator in US dollars, or null for no limit.
     */
    public function monthlyBudgetUsd(): ?float
    {
        $value = $this->all()['monthly_budget_usd'] ?? null;

        return $value === null ? null : (float) $value;
    }

    public function set(string $key, mixed $value): void
    {
        SandboxSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{kill_switch: bool, monthly_budget_usd: ?float}
     */
    public function snapshot(): array
    {
        return ['kill_switch' => $this->killed(), 'monthly_budget_usd' => $this->monthlyBudgetUsd()];
    }
}
