<?php

namespace App\Jobs;

use App\Engine\Trigger\TriggerEventDispatcher;
use App\Models\Trigger;
use Cron\CronExpression;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CheckScheduledTriggersJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('triggers');
    }

    public function handle(TriggerEventDispatcher $dispatcher): void
    {
        Trigger::query()
            ->where('type', 'scheduled')
            ->where('is_active', true)
            ->where('is_paused', false)
            ->whereNotNull('schedule_expression')
            ->where(function ($query) {
                $query->whereNull('schedule_next_run_at')
                    ->orWhere('schedule_next_run_at', '<=', now());
            })
            ->chunk(100, function ($triggers) use ($dispatcher) {
                foreach ($triggers as $trigger) {
                    try {
                        $cron = new CronExpression($trigger->schedule_expression);
                        $timezone = $trigger->schedule_timezone ?? 'UTC';

                        $dispatcher->dispatch($trigger, [
                            'scheduled_at' => now()->toISOString(),
                            'schedule_expression' => $trigger->schedule_expression,
                        ]);

                        $trigger->update([
                            'schedule_next_run_at' => $cron->getNextRunDate('now', 0, false, $timezone),
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning("Scheduled trigger {$trigger->id} failed", ['error' => $e->getMessage()]);
                    }
                }
            });
    }
}
