<?php

namespace App\Ai;

use Carbon\CarbonInterface;
use Laravel\Ai\Responses\TextResponse;

/**
 * What `CreditMeter` needs to price a model call like a Gumloop chat turn:
 * token usage, which model served it (so $-based pricing can look it up),
 * how many tool calls it made, and how long it ran.
 */
final class ResponseUsage
{
    /**
     * @return array<string, mixed>
     */
    public static function from(TextResponse $response, CarbonInterface $startedAt): array
    {
        return [
            ...$response->usage->toArray(),
            ...$response->meta->toArray(),
            'tool_call_count' => $response->toolCalls->count(),
            'duration_seconds' => $startedAt->diffInSeconds(now()),
        ];
    }

    /**
     * Two parts of one turn (before and after it paused for approvals)
     * as one: counts are added up, anything else (the provider and model)
     * is taken from the later part.
     *
     * @param  array<string, mixed>  $earlier
     * @param  array<string, mixed>  $later
     * @return array<string, mixed>
     */
    public static function combine(array $earlier, array $later): array
    {
        $combined = [...$earlier, ...$later];

        foreach ($combined as $key => $value) {
            if (is_numeric($earlier[$key] ?? null) && is_numeric($later[$key] ?? null)) {
                $combined[$key] = $earlier[$key] + $later[$key];
            }
        }

        return $combined;
    }
}
