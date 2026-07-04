<?php

namespace App\Jobs;

use App\Engine\Trigger\TriggerConcurrencyGuard;
use App\Engine\Trigger\TriggerFilterEvaluator;
use App\Engine\Trigger\TriggerRateLimiter;
use App\Models\TriggerEvent;
use App\Services\ExecutionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessTriggerEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(public readonly string $triggerEventId)
    {
        $this->onQueue('triggers');
    }

    public function handle(
        TriggerFilterEvaluator $filterEvaluator,
        TriggerRateLimiter $rateLimiter,
        TriggerConcurrencyGuard $concurrencyGuard,
        ExecutionService $executionService,
    ): void {
        $event = TriggerEvent::with('trigger.workflow')->find($this->triggerEventId);

        if (! $event || ! $event->isPending()) {
            return;
        }

        $trigger = $event->trigger;

        if (! $trigger || ! $trigger->is_active || $trigger->is_paused) {
            $event->update(['status' => 'skipped', 'processed_at' => now()]);

            return;
        }

        $event->update([
            'status' => 'processing',
            'processing_started_at' => now(),
            'attempts' => $event->attempts + 1,
        ]);

        // Apply guards in order: filters → rate limit → concurrency
        if (! $filterEvaluator->passes($trigger, $event->event_data ?? [])) {
            $event->update(['status' => 'skipped', 'processed_at' => now()]);

            return;
        }

        if (! $rateLimiter->attempt($trigger)) {
            // Re-queue after the rate limit window opens up
            $event->update(['status' => 'pending']);
            self::dispatch($event->id)->onQueue('triggers')->delay($rateLimiter->availableIn($trigger));

            return;
        }

        if (! $concurrencyGuard->canExecute($trigger)) {
            $event->update(['status' => 'pending']);
            self::dispatch($event->id)->onQueue('triggers')->delay(15);

            return;
        }

        $executionService->triggerFromEvent($trigger, $event);

        $trigger->increment('total_executions');
        $event->update(['status' => 'processed', 'processed_at' => now()]);
    }
}
