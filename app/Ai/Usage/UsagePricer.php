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
     * A model with long-context rates (`long_context_threshold`,
     * `long_input`, `long_output`) bills the whole request at them once its
     * input passes the threshold, the way providers price long requests.
     *
     * @param  array{input: float, output: float, cache_read?: float|null, cache_write?: float|null, long_context_threshold?: int|null, long_input?: float|null, long_output?: float|null}  $price
     */
    public function dollars(TokenUsage $usage, array $price): float
    {
        $price = $this->applyLongContext($usage, $price);
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

    /**
     * @param  array{input: float, output: float, cache_read?: float|null, cache_write?: float|null, long_context_threshold?: int|null, long_input?: float|null, long_output?: float|null}  $price
     * @return array{input: float, output: float, cache_read?: float|null, cache_write?: float|null, long_context_threshold?: int|null, long_input?: float|null, long_output?: float|null}
     */
    private function applyLongContext(TokenUsage $usage, array $price): array
    {
        $threshold = $price['long_context_threshold'] ?? null;

        if ($threshold === null || $threshold <= 0 || $usage->inputTokens <= $threshold) {
            return $price;
        }

        $scale = static fn (?float $rate, float $from, ?float $to): ?float => $rate === null || $to === null || $from <= 0 ? $rate : $rate * $to / $from;

        return [
            ...$price,
            'input' => $price['long_input'] ?? $price['input'],
            'output' => $price['long_output'] ?? $price['output'],
            'cache_read' => $scale($price['cache_read'] ?? null, $price['input'], $price['long_input'] ?? null),
            'cache_write' => $scale($price['cache_write'] ?? null, $price['input'], $price['long_input'] ?? null),
        ];
    }
}
