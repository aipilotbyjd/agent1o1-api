<?php

namespace App\Services\SuperAdmin\System;

use App\Models\SuperAdmin\ScheduledCommandRun;
use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

/**
 * Records when each scheduled command started and how it ended, from the
 * scheduler's own events. A failure to record is reported and swallowed: the
 * scheduler must never be slowed or stopped by its own bookkeeping.
 */
class ScheduledCommandRecorder
{
    /**
     * @var array<int, string> run ids keyed by task object id
     */
    private array $open = [];

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(ScheduledTaskStarting::class, fn (ScheduledTaskStarting $event) => $this->guard(fn () => $this->started($event)));
        $events->listen(ScheduledTaskFinished::class, fn (ScheduledTaskFinished $event) => $this->guard(fn () => $this->finished($event->task, $event->runtime)));
        $events->listen(ScheduledBackgroundTaskFinished::class, fn (ScheduledBackgroundTaskFinished $event) => $this->guard(fn () => $this->finished($event->task, null)));
        $events->listen(ScheduledTaskFailed::class, fn (ScheduledTaskFailed $event) => $this->guard(fn () => $this->failed($event)));
    }

    private function started(ScheduledTaskStarting $event): void
    {
        $run = ScheduledCommandRun::query()->create([
            'command' => ScheduledCommandName::for($event->task),
            'started_at' => now(),
        ]);

        $this->open[spl_object_id($event->task)] = $run->id;
    }

    private function finished(object $task, ?float $runtime): void
    {
        $run = $this->take($task);

        if ($run === null) {
            return;
        }

        $run->forceFill([
            'finished_at' => now(),
            'exit_code' => $task->exitCode ?? 0,
            'duration_ms' => (int) round(($runtime ?? $run->started_at->diffInSeconds(now(), true)) * 1000),
        ])->save();
    }

    private function failed(ScheduledTaskFailed $event): void
    {
        $run = $this->take($event->task);

        if ($run === null) {
            return;
        }

        $run->forceFill([
            'finished_at' => now(),
            'exit_code' => $event->task->exitCode ?? 1,
            'duration_ms' => (int) round($run->started_at->diffInMilliseconds(now(), true)),
            'error' => str($event->exception->getMessage())->limit(500, '')->toString(),
        ])->save();
    }

    private function take(object $task): ?ScheduledCommandRun
    {
        $id = $this->open[spl_object_id($task)] ?? null;
        unset($this->open[spl_object_id($task)]);

        return $id === null ? null : ScheduledCommandRun::query()->find($id);
    }

    private function guard(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
