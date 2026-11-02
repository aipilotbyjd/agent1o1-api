<?php

namespace App\Ai\Usage;

/**
 * Turns a call's token counts into US dollars with a model's price per
 * million tokens, billing each kind of token at its own rate: uncached
 * input at the input price, cache reads and cache writes at the cache
 * prices (or the input price scaled by `billing.cache_price_fallback`
 * when the model has none), and all output, reasoning included, at the
 * output price.
 */
class UsagePricer
{
    /**
     * @param  array{input: float, output: float, cache_read?: float|null, cache_write?: float|null}  $price
     */
    public function dollars(TokenUsage $usage, array $price): float
    {
        $cacheRead = $price['cache_read'] ?? $price['input'] * (float) config('billing.cache_price_fallback.read', 1.0);
        $cacheWrite = $price['cache_write'] ?? $price['input'] * (float) config('billing.cache_price_fallback.write', 1.0);

        return ($usage->uncachedInputTokens() * $price['input']
            + $usage->cacheReadInputTokens * $cacheRead
            + $usage->cacheWriteInputTokens * $cacheWrite
            + $usage->outputTokens * $price['output']) / 1_000_000;
    }

    /**
     * What the call cost under Laravel AI 0.x, which only reported
     * uncached input and non-reasoning output: for shadow billing.
     *
     * @param  array{input: float, output: float, cache_read?: float|null, cache_write?: float|null}  $price
     */
    public function legacyDollars(TokenUsage $usage, array $price): float
    {
        return ($usage->uncachedInputTokens() * $price['input']
            + $usage->visibleOutputTokens() * $price['output']) / 1_000_000;
    }
}
