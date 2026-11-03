<?php

namespace App\Services\Intelligence;

use App\Ai\Usage\TokenUsage;
use App\Ai\Usage\UsagePricer;
use App\Services\Ai\ModelPrices;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * "What if the workflow builder ran on Haiku instead of Sonnet?" Takes the
 * real tokens a model (optionally only for one feature) used over the last
 * `$days` days on the platform's key and prices them on another model, next
 * to that model's recent reliability and speed. The quality side is the
 * agent eval suite's to answer, so the result says to run it before
 * switching.
 */
class RoutingSimulator
{
    public function __construct(
        private readonly ModelPrices $prices,
        private readonly UsagePricer $pricer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function simulate(string $fromProvider, string $fromModel, string $toProvider, string $toModel, ?string $feature = null, int $days = 30): array
    {
        $target = $this->prices->for($toProvider, $toModel);

        if ($target === null) {
            throw new InvalidArgumentException("[{$toProvider}:{$toModel}] has no price, so its cost cannot be estimated.");
        }

        $days = max(1, min($days, 180));
        $since = now()->subDays($days)->toDateString();

        $usage = DB::table('fact_usage_daily')
            ->where('date', '>=', $since)
            ->where('provider', $fromProvider)
            ->where('model', $fromModel)
            ->where('is_byok', false)
            ->when($feature !== null, fn ($query) => $query->where('feature', $feature))
            ->selectRaw('sum(calls) as calls, sum(input_tokens) as input, sum(cache_read_tokens) as cached, sum(output_tokens) as output, sum(provider_cost_usd) as cost')
            ->first();

        $tokens = new TokenUsage(
            inputTokens: (int) ($usage->input ?? 0),
            outputTokens: (int) ($usage->output ?? 0),
            cacheReadInputTokens: (int) ($usage->cached ?? 0),
        );

        $currentCost = (float) ($usage->cost ?? 0);
        $projectedCost = $this->pricer->dollars($tokens, $target);

        $targetHealth = DB::table('fact_usage_daily')
            ->where('date', '>=', $since)
            ->where('provider', $toProvider)
            ->where('model', $toModel)
            ->selectRaw('sum(calls) as calls, sum(failed_calls) as failed, avg(avg_latency_ms) as latency')
            ->first();

        return [
            'from' => "{$fromProvider}:{$fromModel}",
            'to' => "{$toProvider}:{$toModel}",
            'feature' => $feature,
            'days' => $days,
            'calls' => (int) ($usage->calls ?? 0),
            'input_tokens' => $tokens->inputTokens,
            'output_tokens' => $tokens->outputTokens,
            'current_cost_usd' => round($currentCost, 2),
            'projected_cost_usd' => round($projectedCost, 2),
            'saving_usd' => round($currentCost - $projectedCost, 2),
            'saving_percent' => $currentCost > 0 ? round(($currentCost - $projectedCost) / $currentCost * 100, 1) : null,
            'monthly_saving_usd' => round(($currentCost - $projectedCost) / $days * 30, 2),
            'target_price_status' => $this->prices->status($toProvider, $toModel)?->value,
            'target_recent_calls' => (int) ($targetHealth->calls ?? 0),
            'target_failure_rate' => (int) ($targetHealth->calls ?? 0) > 0 ? round((int) $targetHealth->failed / (int) $targetHealth->calls, 4) : null,
            'target_avg_latency_ms' => $targetHealth->latency === null ? null : (int) round((float) $targetHealth->latency),
            'quality_note' => 'Cost only. Run the affected agents\' eval suites on the target model before switching.',
        ];
    }
}
