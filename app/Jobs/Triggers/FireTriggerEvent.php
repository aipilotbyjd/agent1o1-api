<?php

namespace App\Jobs\Triggers;

use App\Enums\Triggers\TriggerEventStatus;
use App\Enums\Triggers\TriggerTargetType;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerEvent;
use App\Services\Triggers\TriggerService;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Starts the run for one queued trigger event: the slow half of a firing.
 *
 * Safe to dispatch more than once for the same event: only one worker can
 * claim it, and an event that already settled is left alone. Retries with
 * backoff; when they run out, the event is marked failed and counts toward
 * the trigger's circuit breaker.
 */
class FireTriggerEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * How many exceptions the job may throw before it gives up.
     *
     * Exceptions rather than attempts: WithoutOverlapping puts the job back on
     * the queue when the lock is busy, which uses up an attempt without the
     * job running. Counting exceptions means a busy lock alone can never use
     * up the job's retries.
     */
    public int $maxExceptions;

    /**
     * Seconds the job may run: minutes for an agent target, seconds for a
     * workflow.
     */
    public int $timeout;

    /**
     * Queue the firing on the right queue for its target.
     */
    public function __construct(
        public Trigger $trigger,
        public TriggerEvent $event,
    ) {
        // An agent firing waits on a model call, so it runs on the agent queue
        // with a longer timeout. A webhook-fired workflow event must never sit
        // behind an LLM call.
        $isAgentTarget = $this->trigger->target_type === TriggerTargetType::Agent->value;

        $this->maxExceptions = (int) config('triggers.fire_max_exceptions');
        $this->timeout = (int) config($isAgentTarget ? 'triggers.agent_fire_timeout_seconds' : 'triggers.fire_timeout_seconds');
        $this->onQueue((string) config($isAgentTarget ? 'triggers.agent_fire_queue' : 'triggers.fire_queue'));
    }

    /**
     * One worker per event at a time.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping((string) $this->event->id))
                ->releaseAfter((int) config('triggers.fire_lock.release_after_seconds'))
                ->expireAfter((int) config('triggers.fire_lock.expire_after_seconds')),
        ];
    }

    /**
     * Seconds to wait before each retry (`triggers.fire_backoff_seconds`).
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return (array) config('triggers.fire_backoff_seconds');
    }

    /**
     * Keep trying for an hour: an event waiting on a concurrency cap goes back
     * on the queue, and each of those would otherwise use up one of the job's
     * attempts. The exception limit still ends a job that keeps failing.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHour();
    }

    /**
     * Claim the event and start its run.
     */
    public function handle(TriggerService $triggers): void
    {
        $this->event->refresh();

        // Over a concurrency cap: leave the event queued and look again soon.
        if ($this->event->status === TriggerEventStatus::Queued && $triggers->atConcurrencyLimit($this->trigger)) {
            $this->release((int) config('triggers.concurrency_release_seconds'));

            return;
        }

        // Over the trigger's own runs-per-minute limit: wait for the next slot.
        $perMinute = (int) ($this->trigger->config['max_runs_per_minute'] ?? 0);
        $rateKey = "trigger-rate:{$this->trigger->getKey()}";

        if ($perMinute > 0 && $this->event->status === TriggerEventStatus::Queued && RateLimiter::tooManyAttempts($rateKey, $perMinute)) {
            $this->release(max(1, RateLimiter::availableIn($rateKey)));

            return;
        }

        // An event that already settled, or that another worker holds, can't
        // be claimed: this copy does nothing, so a re-dispatch never starts a
        // second run.
        if (! $this->event->claim()) {
            return;
        }

        if ($perMinute > 0) {
            RateLimiter::hit($rateKey, 60);
        }

        $this->event->increment('attempts');

        try {
            $triggers->fire($this->trigger, $this->event);
        } catch (Throwable $exception) {
            // Hand the event back to `queued` so the retry can claim it again.
            // One that already fired stays fired.
            $this->event->refresh();

            if ($this->event->status !== TriggerEventStatus::Fired) {
                $this->event->requeue();
            }

            throw $exception;
        }
    }

    /**
     * Mark the event failed once every retry is spent, and count it toward
     * the trigger's circuit breaker.
     */
    public function failed(?Throwable $exception): void
    {
        $this->event->refresh();

        if ($this->event->status === TriggerEventStatus::Fired) {
            return;
        }

        $this->event->finish(TriggerEventStatus::Failed, $exception?->getMessage());

        // An event that was never attempted (attempts 0) only waited too long
        // in the queue. That isn't a broken trigger, so it doesn't count.
        if ($this->event->attempts > 0) {
            app(TriggerService::class)->recordFailure($this->trigger);
        }
    }
}
