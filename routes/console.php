<?php

use App\Jobs\CheckScheduledTriggersJob;
use App\Jobs\PollTriggersJob;
use Illuminate\Support\Facades\Schedule;

Schedule::command('horizon:snapshot')->everyFiveMinutes();

Schedule::command('billing:rollover')->dailyAt('00:10')->withoutOverlapping();
Schedule::command('billing:reconcile-redis')->dailyAt('04:00');
Schedule::command('billing:expire-trials')->hourly();

// Unified trigger engine — scheduled + polling triggers for both workflows and
// agents run through the same jobs (target resolved per Trigger).
Schedule::job(new PollTriggersJob)->everyMinute()->withoutOverlapping();
Schedule::job(new CheckScheduledTriggersJob)->everyMinute()->withoutOverlapping();

// Execution observability maintenance
Schedule::command('executions:archive-logs')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('executions:prune')->dailyAt('02:30')->withoutOverlapping();

// AI workflow builder cleanup
Schedule::command('workflow-builder:cleanup')->dailyAt('03:00')->withoutOverlapping();
