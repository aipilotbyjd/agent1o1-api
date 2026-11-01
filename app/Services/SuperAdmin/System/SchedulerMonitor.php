<?php

namespace App\Services\SuperAdmin\System;

use App\Models\SuperAdmin\ScheduledCommandRun;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Whether the scheduler is alive and whether each command is running when it
 * should. Liveness comes from a heartbeat written by the `system:heartbeat`
 * command every minute; per-command history from `ScheduledCommandRecorder`.
 */
class SchedulerMonitor
{
    public const HEARTBEAT_KEY = 'system:scheduler-heartbeat';

    public function beat(): void
    {
        Cache::forever(self::HEARTBEAT_KEY, now()->getTimestamp());
    }

    public function heartbeatAt(): ?Carbon
    {
        $timestamp = Cache::get(self::HEARTBEAT_KEY);

        return $timestamp === null ? null : Carbon::createFromTimestamp((int) $timestamp);
    }

    /**
     * Every scheduled command with how often it should run and when it last did.
     *
     * @return list<array{command: string, expression: string, expected_every_seconds: ?int, last_started_at: ?string, last_status: string, last_exit_code: ?int, last_duration_ms: ?int, last_error: ?string, active: bool, overdue: bool}>
     */
    public function commands(): array
    {
        // The schedule is defined in routes/console.php, which a web request has not loaded yet.
        app(ConsoleKernel::class)->all();

        $latest = $this->latestRuns();
        $factor = (float) config('super_admin_health.scheduler.overdue_factor');
        $grace = (int) config('super_admin_health.scheduler.overdue_grace_seconds');

        $rows = [];

        foreach (app(Schedule::class)->events() as $event) {
            $name = ScheduledCommandName::for($event);
            $run = $latest[$name] ?? null;
            $interval = $this->interval($event);
            $active = $this->active($event);

            $overdue = $active && $run !== null && $interval !== null
                && $run->started_at->diffInSeconds(now(), true) > $interval * $factor + $grace;

            $rows[] = [
                'command' => $name,
                'expression' => $event->expression,
                'expected_every_seconds' => $interval,
                'last_started_at' => $run?->started_at->toIso8601String(),
                'last_status' => match (true) {
                    $run === null => 'never_run',
                    $run->finished_at === null => 'running',
                    ($run->exit_code ?? 0) === 0 => 'ok',
                    default => 'failed',
                },
                'last_exit_code' => $run?->exit_code,
                'last_duration_ms' => $run?->duration_ms,
                'last_error' => $run?->error,
                'active' => $active,
                'overdue' => $overdue,
            ];
        }

        return $rows;
    }

    public function prune(): int
    {
        return ScheduledCommandRun::query()
            ->where('started_at', '<', now()->subDays((int) config('super_admin_health.scheduler.keep_runs_days')))
            ->delete();
    }

    /**
     * @return array<string, ScheduledCommandRun>
     */
    private function latestRuns(): array
    {
        $latest = ScheduledCommandRun::query()
            ->selectRaw('command, max(started_at) as last_started')
            ->groupBy('command')
            ->pluck('last_started', 'command');

        if ($latest->isEmpty()) {
            return [];
        }

        $runs = ScheduledCommandRun::query()
            ->whereIn('command', $latest->keys())
            ->where('started_at', '>=', Carbon::parse($latest->min())->subSecond())
            ->orderBy('started_at')
            ->get();

        $byCommand = [];

        foreach ($runs as $run) {
            $byCommand[$run->command] = $run;
        }

        return $byCommand;
    }

    private function interval(Event $event): ?int
    {
        try {
            $first = $event->nextRunDate(now());
            $second = $event->nextRunDate($first->copy()->addSecond());

            return (int) $first->diffInSeconds($second, true);
        } catch (Throwable) {
            return null;
        }
    }

    private function active(Event $event): bool
    {
        try {
            return $event->filtersPass(app());
        } catch (Throwable) {
            return true;
        }
    }
}
