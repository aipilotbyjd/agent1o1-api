<?php

namespace App\Services\Ai;

use App\Models\Ai\AiProviderPause;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Which providers the platform has paused. Asked on the path of every model
 * call, so the (tiny) table is cached and dropped whenever it changes. If it
 * cannot be read at all (a database that is down, a deploy that has not
 * migrated yet) nothing is treated as paused: a switch that is only ever
 * wanted for a short time must not be what stops every call when it breaks.
 */
class ProviderPauses
{
    public const string CACHE_KEY = 'ai-provider-pauses';

    private const int CACHE_TTL_SECONDS = 300;

    public function isPaused(string $provider): bool
    {
        return array_key_exists($provider, $this->all());
    }

    /**
     * @return array<string, string> provider => the reason it was paused
     */
    public function all(): array
    {
        try {
            return Cache::remember(
                self::CACHE_KEY,
                self::CACHE_TTL_SECONDS,
                fn (): array => AiProviderPause::query()->pluck('reason', 'provider')->all(),
            );
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
