<?php

namespace App\Ai\Usage;

use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\Usage;

/**
 * One model call's token counts, whichever shape they were stored in.
 *
 * Laravel AI 1.x reports inclusive counts under `input_tokens` /
 * `output_tokens`: input includes cache reads and cache writes, output
 * includes reasoning. Rows written before the upgrade carry
 * `prompt_tokens` / `completion_tokens`, which left those categories out,
 * so they are added back here. Everything that prices or reports usage
 * reads it through this class instead of picking keys off the array.
 */
final readonly class TokenUsage
{
    public function __construct(
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cacheReadInputTokens = 0,
        public int $cacheWriteInputTokens = 0,
        public int $reasoningTokens = 0,
    ) {}

    /**
     * @param  array<string, mixed>|null  $usage
     */
    public static function fromArray(?array $usage): self
    {
        $usage ??= [];

        $cacheRead = (int) ($usage['cache_read_input_tokens'] ?? 0);
        $cacheWrite = (int) ($usage['cache_write_input_tokens'] ?? 0);
        $reasoning = (int) ($usage['reasoning_tokens'] ?? 0);

        if (array_key_exists('input_tokens', $usage) || array_key_exists('output_tokens', $usage)) {
            return new self(
                inputTokens: (int) ($usage['input_tokens'] ?? 0),
                outputTokens: (int) ($usage['output_tokens'] ?? 0),
                cacheReadInputTokens: $cacheRead,
                cacheWriteInputTokens: $cacheWrite,
                reasoningTokens: $reasoning,
            );
        }

        return new self(
            inputTokens: (int) ($usage['prompt_tokens'] ?? 0) + $cacheRead + $cacheWrite,
            outputTokens: (int) ($usage['completion_tokens'] ?? 0) + $reasoning,
            cacheReadInputTokens: $cacheRead,
            cacheWriteInputTokens: $cacheWrite,
            reasoningTokens: $reasoning,
        );
    }

    public static function fromSdk(Usage $usage): self
    {
        return new self(
            inputTokens: $usage->inputTokens,
            outputTokens: $usage->outputTokens,
            cacheReadInputTokens: $usage instanceof TextUsage ? (int) ($usage->cacheReadInputTokens ?? 0) : 0,
            cacheWriteInputTokens: $usage instanceof TextUsage ? (int) ($usage->cacheWriteInputTokens ?? 0) : 0,
            reasoningTokens: $usage instanceof TextUsage ? (int) ($usage->reasoningTokens ?? 0) : 0,
        );
    }

    /**
     * Input tokens billed at the model's base input rate.
     */
    public function uncachedInputTokens(): int
    {
        return max(0, $this->inputTokens - $this->cacheReadInputTokens - $this->cacheWriteInputTokens);
    }

    /**
     * Output tokens other than reasoning: what `completion_tokens` meant
     * before the upgrade.
     */
    public function visibleOutputTokens(): int
    {
        return max(0, $this->outputTokens - $this->reasoningTokens);
    }

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    public function add(self $other): self
    {
        return new self(
            $this->inputTokens + $other->inputTokens,
            $this->outputTokens + $other->outputTokens,
            $this->cacheReadInputTokens + $other->cacheReadInputTokens,
            $this->cacheWriteInputTokens + $other->cacheWriteInputTokens,
            $this->reasoningTokens + $other->reasoningTokens,
        );
    }

    /**
     * The 1.x storage shape.
     *
     * @return array{input_tokens: int, output_tokens: int, cache_read_input_tokens: int, cache_write_input_tokens: int, reasoning_tokens: int}
     */
    public function toArray(): array
    {
        return [
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'cache_read_input_tokens' => $this->cacheReadInputTokens,
            'cache_write_input_tokens' => $this->cacheWriteInputTokens,
            'reasoning_tokens' => $this->reasoningTokens,
        ];
    }

    /**
     * The 1.x shape plus the pre-upgrade `prompt_tokens` /
     * `completion_tokens` keys, for API responses whose clients have not
     * moved to the new names yet.
     *
     * @return array{input_tokens: int, output_tokens: int, cache_read_input_tokens: int, cache_write_input_tokens: int, reasoning_tokens: int, prompt_tokens: int, completion_tokens: int}
     */
    public function toArrayWithLegacyKeys(): array
    {
        return [
            ...$this->toArray(),
            'prompt_tokens' => $this->uncachedInputTokens(),
            'completion_tokens' => $this->visibleOutputTokens(),
        ];
    }
}
