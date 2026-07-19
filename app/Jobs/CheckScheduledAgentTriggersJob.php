<?php

namespace App\Jobs;

use App\Models\AgentTrigger;
use Cron\CronExpression;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Scans active `schedule`-type agent triggers every minute and fires the ones
 * whose cron expression is due, dispatching a RunAgentJob for each. Mirrors the
 * workflow engine's CheckScheduledTriggersJob but for standalone agents.
 */
class CheckScheduledAgentTriggersJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('agents');
    }

    public function handle(): void
    {
        AgentTrigger::query()
            ->where('type', 'schedule')
            ->where('is_active', true)
            ->with('agent')
            ->chunkById(100, function ($triggers) {
                foreach ($triggers as $trigger) {
                    $this->fireIfDue($trigger);
                }
            });
    }

    private function fireIfDue(AgentTrigger $trigger): void
    {
        $cron = $trigger->config['cron'] ?? null;

        if (! $cron || ! $trigger->agent || ! $trigger->agent->is_active) {
            return;
        }

        $timezone = $trigger->config['timezone'] ?? config('app.timezone', 'UTC');

        try {
            $expression = new CronExpression($cron);
            $now = Carbon::now($timezone);

            if (! $expression->isDue($now)) {
                return;
            }

            // Guard against double-firing within the same minute if this job overlaps.
            if ($trigger->last_fired_at && $trigger->last_fired_at->copy()->timezone($timezone)->isSameMinute($now)) {
                return;
            }
        } catch (Throwable $e) {
            Log::warning('Invalid agent trigger cron expression.', [
                'trigger_id' => $trigger->id,
                'cron' => $cron,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $trigger->forceFill(['last_fired_at' => now()])->save();

        RunAgentJob::dispatch(
            $trigger->agent_id,
            $trigger->initial_message ?? '',
            $trigger->id,
            ['source' => 'schedule'],
        );
    }
}
