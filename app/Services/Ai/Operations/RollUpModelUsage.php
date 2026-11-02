<?php

namespace App\Services\Ai\Operations;

use App\Ai\Usage\TokenUsage;
use App\Ai\Usage\UsagePricer;
use App\Models\Ai\ModelUsageDaily;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Ai\ModelPrices;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rebuilds `model_usage_daily` for the latest days from the usage records of
 * workflow node runs and agent chat messages (the two things that spend most).
 *
 * A day is rebuilt whole, in one transaction, rather than added to: a record's
 * usage is written when its call finishes, which may be after the day it was
 * created, and rebuilding makes running it twice harmless. Costs use the prices
 * in force when it runs, so a day rebuilt after a price change is re-costed;
 * backfill older days with a larger window.
 */
class RollUpModelUsage
{
    public const int MAX_DAYS = 31;

    /** Rows written per insert. */
    private const int INSERT_CHUNK = 500;

    public function __construct(
        private readonly ModelPrices $prices,
        private readonly UsagePricer $pricer,
    ) {}

    /**
     * @return array{days: int, rows: int}
     */
    public function run(?int $days = null): array
    {
        $days = max(1, min($days ?? (int) config('model_operations.rollup_days'), self::MAX_DAYS));
        $rows = 0;

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $rows += $this->rollUpDay(today()->subDays($offset));
        }

        return ['days' => $days, 'rows' => $rows];
    }

    private function rollUpDay(CarbonInterface $day): int
    {
        $start = Carbon::instance($day)->startOfDay();
        $end = $start->copy()->addDay();

        $groups = [];

        foreach ([$this->nodeRunUsage($start, $end), $this->messageUsage($start, $end)] as $rows) {
            foreach ($rows as $row) {
                $this->add($groups, $row);
            }
        }

        $records = array_map(fn (array $group): array => $this->record($start, $group), array_values($groups));

        DB::transaction(function () use ($start, $records): void {
            ModelUsageDaily::query()->where('date', $start->toDateString())->delete();

            foreach (array_chunk($records, self::INSERT_CHUNK) as $chunk) {
                ModelUsageDaily::query()->insert($chunk);
            }
        });

        return count($records);
    }

    /**
     * @return \Generator<int, object>
     */
    private function nodeRunUsage(CarbonInterface $start, CarbonInterface $end): \Generator
    {
        yield from DB::table('node_runs')
            ->join('runs', 'runs.id', '=', 'node_runs.run_id')
            ->whereNotNull('node_runs.usage')
            ->where('node_runs.created_at', '>=', $start)
            ->where('node_runs.created_at', '<', $end)
            ->select('runs.workspace_id as workspace_id', ...$this->usageColumns('node_runs'))
            ->cursor();
    }

    /**
     * @return \Generator<int, object>
     */
    private function messageUsage(CarbonInterface $start, CarbonInterface $end): \Generator
    {
        yield from DB::table('agent_messages')
            ->join('agent_sessions', 'agent_sessions.id', '=', 'agent_messages.agent_session_id')
            ->whereNotNull('agent_messages.usage')
            ->where('agent_messages.created_at', '>=', $start)
            ->where('agent_messages.created_at', '<', $end)
            ->select('agent_sessions.workspace_id as workspace_id', ...$this->usageColumns('agent_messages'))
            ->cursor();
    }

    /**
     * @return array<int, string>
     */
    private function usageColumns(string $table): array
    {
        return [
            "{$table}.usage->provider as provider",
            "{$table}.usage->model as model",
            "{$table}.usage as usage",
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     */
    private function add(array &$groups, object $row): void
    {
        $provider = (string) ($row->provider ?? '');
        $model = (string) ($row->model ?? '');

        if ($row->workspace_id === null || ($provider === '' && $model === '')) {
            return;
        }

        $ownKey = ByokProviderRegistrar::isByok($provider);
        $provider = $ownKey ? 'own-key' : $provider;
        $key = "{$row->workspace_id}|{$provider}|{$model}";

        $groups[$key] ??= [
            'workspace_id' => $row->workspace_id,
            'provider' => $provider,
            'model' => $model,
            'own_key' => $ownKey,
            'calls' => 0,
            'tokens' => new TokenUsage,
        ];

        $groups[$key]['calls']++;
        $groups[$key]['tokens'] = $groups[$key]['tokens']->add(
            TokenUsage::fromArray(is_string($row->usage) ? json_decode($row->usage, true) : (array) $row->usage),
        );
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array<string, mixed>
     */
    private function record(CarbonInterface $day, array $group): array
    {
        $price = $group['own_key'] ? null : $this->prices->for($group['provider'], $group['model']);

        /** @var TokenUsage $tokens */
        $tokens = $group['tokens'];
        unset($group['tokens']);

        return [
            'id' => (string) Str::orderedUuid(),
            'date' => $day->toDateString(),
            ...$group,
            'input_tokens' => $tokens->inputTokens,
            'output_tokens' => $tokens->outputTokens,
            'cache_read_tokens' => $tokens->cacheReadInputTokens,
            'cache_write_tokens' => $tokens->cacheWriteInputTokens,
            'reasoning_tokens' => $tokens->reasoningTokens,
            'prompt_tokens' => $tokens->uncachedInputTokens(),
            'completion_tokens' => $tokens->visibleOutputTokens(),
            'estimated_cost_usd' => match (true) {
                $group['own_key'] => 0.0,
                $price === null => null,
                default => $this->pricer->dollars($tokens, $price),
            },
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
