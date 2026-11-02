<?php

namespace App\Ai\Observability;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;

/**
 * The Pulse dashboard card for what `AiActivityRecorder` records, plus the
 * shadow billing difference `CreditMeter` records: per model, how many
 * steps ran, how long they took, how much of the input the prompt cache
 * served, how often it was failed over from and how many more credits it
 * cost than before the Laravel AI 1.x upgrade; per tool, how often it ran,
 * how long it took and how often it failed.
 */
#[Lazy]
class AiActivityCard extends Card
{
    #[Url(as: 'ai-activity')]
    public string $show = 'models';

    public function render(): Renderable
    {
        [$entries, $time, $runAt] = $this->remember(
            fn (): Collection => $this->show === 'tools' ? $this->tools() : $this->models(),
            $this->show,
        );

        return View::make('pulse.ai-activity', [
            'time' => $time,
            'runAt' => $runAt,
            'entries' => $entries,
        ]);
    }

    /**
     * @return Collection<int, object>
     */
    private function models(): Collection
    {
        $inputTokens = $this->aggregate('ai_input_tokens', 'sum')->pluck('sum', 'key');
        $cacheReads = $this->aggregate('ai_cache_read_tokens', 'sum')->pluck('sum', 'key');
        $failovers = $this->aggregate('ai_failover', 'count')->pluck('count', 'key');
        $shadowDelta = $this->aggregate('ai_billing_shadow_delta', 'sum')->pluck('sum', 'key');

        return $this->aggregate('ai_step', ['count', 'avg', 'max'], 'count')
            ->map(fn (object $row): object => (object) [
                'key' => $row->key,
                'count' => (int) $row->count,
                'avg' => (int) round((float) $row->avg),
                'max' => (int) $row->max,
                'cache_hit_rate' => ($input = (int) ($inputTokens[$row->key] ?? 0)) > 0
                    ? round((int) ($cacheReads[$row->key] ?? 0) / $input * 100)
                    : null,
                'failovers' => (int) ($failovers[$row->key] ?? 0),
                'credit_delta' => (int) ($shadowDelta[$row->key] ?? 0),
            ]);
    }

    /**
     * @return Collection<int, object>
     */
    private function tools(): Collection
    {
        $failures = $this->aggregate('ai_tool_failure', 'count')->pluck('count', 'key');

        return $this->aggregate('ai_tool', ['count', 'avg', 'max'], 'count')
            ->map(fn (object $row): object => (object) [
                'key' => $row->key,
                'count' => (int) $row->count,
                'avg' => (int) round((float) $row->avg),
                'max' => (int) $row->max,
                'failures' => (int) ($failures[$row->key] ?? 0),
            ]);
    }
}
