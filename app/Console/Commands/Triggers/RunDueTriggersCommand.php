<?php

namespace App\Console\Commands\Triggers;

use App\Enums\Triggers\TriggerType;
use App\Jobs\Triggers\PollTrigger;
use App\Models\Triggers\Trigger;
use App\Services\Triggers\TriggerService;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The every-minute tick: queues an event for each due schedule and a poll for
 * each due polling trigger.
 *
 * It starts no runs itself, so the tick costs about the same whether one
 * trigger is due or a thousand.
 */
class RunDueTriggersCommand extends Command
{
    protected $signature = 'triggers:run-due';

    protected $description = 'Queue an event for every due schedule trigger and dispatch a poll for every due polling trigger.';

    /**
     * Queue everything that's due and report how much.
     */
    public function handle(TriggerService $triggers): int
    {
        $queued = $this->queueDueSchedules($triggers) + $this->queueDuePolls();

        $this->info("Queued {$queued} trigger event(s).");

        return self::SUCCESS;
    }

    /**
     * Fire every schedule whose `next_run_at` has arrived, and move it to its
     * next slot.
     *
     * A slot missed while the scheduler was down fires once if it's within
     * `triggers.schedule_catch_up_minutes`, and is skipped if it's older.
     * Either way it fires at most once, however many slots were missed.
     */
    private function queueDueSchedules(TriggerService $triggers): int
    {
        $now = now();
        $catchUpCutoff = $now->copy()->subMinutes(max(0, (int) config('triggers.schedule_catch_up_minutes')));
        $queued = 0;

        // Schedules saved before `next_run_at` existed get one now. One with an
        // invalid cron keeps none and is never due.
        Trigger::query()
            ->where('type', TriggerType::Schedule)
            ->where('is_active', true)
            ->whereNull('next_run_at')
            ->with('workspace')
            ->eachById(function (Trigger $trigger): void {
                if ($trigger->nextRun(now(), allowCurrentMinute: true) === null) {
                    Log::warning('Schedule trigger skipped: invalid cron expression.', ['trigger_id' => $trigger->id]);

                    return;
                }

                $trigger->save();
            });

        Trigger::query()
            ->where('type', TriggerType::Schedule)
            ->where('is_active', true)
            ->where('next_run_at', '<=', $now)
            ->whereHas('workspace', fn ($workspaces) => $workspaces->whereNull('suspended_at'))
            ->with(['workspace.subscriptions.plan', 'target'])
            ->eachById(function (Trigger $trigger) use ($triggers, $now, $catchUpCutoff, &$queued): void {
                $slot = $trigger->next_run_at;

                // Move on first, so if intake throws, the next tick doesn't retry
                // the same slot forever.
                $trigger->forceFill(['next_run_at' => $trigger->nextRun($now)])->save();

                // A one-time schedule has no next slot: it switches itself off,
                // whether the slot fires below or was missed. Only after intake,
                // which skips an inactive trigger: switching it off first meant
                // its one slot was always recorded as skipped and never ran. A
                // tick that dies in between leaves it due, and the next tick's
                // intake finds the slot already recorded (its delivery id).
                try {
                    if ($this->fireSlot($triggers, $trigger, $slot, $catchUpCutoff)) {
                        $queued++;
                    }
                } finally {
                    if ($trigger->isOneTimeSchedule()) {
                        $trigger->forceFill(['is_active' => false])->save();
                    }
                }
            });

        return $queued;
    }

    /**
     * Hands one due slot to intake, unless it was missed by more than the
     * catch-up window. Whether it was handed over.
     */
    private function fireSlot(TriggerService $triggers, Trigger $trigger, CarbonInterface $slot, CarbonInterface $catchUpCutoff): bool
    {
        if ($slot->lt($catchUpCutoff)) {
            Log::info('Schedule trigger slot missed beyond the catch-up window.', ['trigger_id' => $trigger->id, 'slot' => $slot->toIso8601String()]);

            return false;
        }

        // The delivery id names the slot, so a second scheduler or a
        // re-run of this tick hits the unique index instead of firing
        // twice. Unlike a lock, that holds across servers.
        try {
            $triggers->receive(
                $trigger,
                TriggerType::Schedule,
                ['scheduled_at' => $slot->toIso8601String()],
                deliveryId: 'schedule:'.$slot->format('Y-m-d H:i'),
            );
        } catch (Throwable $exception) {
            // One trigger's failure must never stop the tick for every
            // other workspace's schedules.
            report($exception);

            return false;
        }

        return true;
    }

    /**
     * Queue a poll for every polling trigger whose interval has passed since
     * its last poll.
     */
    private function queueDuePolls(): int
    {
        $queued = 0;

        Trigger::query()
            ->where('type', TriggerType::Polling)
            ->where('is_active', true)
            ->whereHas('workspace', fn ($workspaces) => $workspaces->whereNull('suspended_at'))
            ->where(fn ($due) => $due->whereNull('next_poll_at')->orWhere('next_poll_at', '<=', now()))
            ->with(['subscription', 'workspace.subscriptions.plan'])
            ->eachById(function (Trigger $trigger) use (&$queued): void {
                // Claimed before the job runs, so a slow poll isn't queued again
                // on the next tick. The poll sets the real time when it ends.
                $trigger->forceFill(['next_poll_at' => $trigger->nextPollTime()])->saveQuietly();

                PollTrigger::dispatch($trigger);
                $queued++;
            });

        return $queued;
    }
}
