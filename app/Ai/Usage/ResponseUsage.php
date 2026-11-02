<?php

namespace App\Ai\Usage;

use Carbon\CarbonInterface;
use Laravel\Ai\Responses\TextResponse;

/**
 * What `CreditMeter` needs to price a model call like a Gumloop chat turn:
 * token usage, which model served it (so $-based pricing can look it up),
 * how many tool calls it made, and how long it ran.
 *
 * Token counts are stored in the Laravel AI 1.x shape (`TokenUsage`).
 */
final class ResponseUsage
{
    /**
     * Keys that hold token counts, in either the 1.x or the pre-upgrade shape.
     */
    private const array TOKEN_KEYS = [
        'input_tokens', 'output_tokens', 'cache_read_input_tokens', 'cache_write_input_tokens',
        'reasoning_tokens', 'prompt_tokens', 'completion_tokens',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function from(TextResponse $response, CarbonInterface $startedAt): array
    {
        return [
            ...TokenUsage::fromSdk($response->usage)->toArray(),
            ...$response->meta->toArray(),
            'tool_call_count' => $response->toolCalls->count(),
            'duration_seconds' => $startedAt->diffInSeconds(now()),
        ];
    }

    /**
     * Two parts of one turn (before and after it paused for approvals)
     * as one: counts are added up, anything else (the provider and model)
     * is taken from the later part. Token counts are added through
     * `TokenUsage`, so a turn paused before the upgrade and resumed after it
     * still adds up.
     *
     * @param  array<string, mixed>  $earlier
     * @param  array<string, mixed>  $later
     * @return array<string, mixed>
     */
    public static function combine(array $earlier, array $later): array
    {
        $tokens = TokenUsage::fromArray($earlier)->add(TokenUsage::fromArray($later));

        $earlier = array_diff_key($earlier, array_flip(self::TOKEN_KEYS));
        $later = array_diff_key($later, array_flip(self::TOKEN_KEYS));

        $combined = [...$earlier, ...$later];

        foreach ($combined as $key => $value) {
            if (is_numeric($earlier[$key] ?? null) && is_numeric($later[$key] ?? null)) {
                $combined[$key] = $earlier[$key] + $later[$key];
            }
        }

        return [...$tokens->toArray(), ...$combined];
    }
}
