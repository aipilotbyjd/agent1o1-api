<?php

use App\Jobs\CheckScheduledAgentTriggersJob;
use App\Jobs\CheckScheduledTriggersJob;
use App\Jobs\PollTriggersJob;
use Illuminate\Support\Facades\Schedule;

Schedule::command('horizon:snapshot')->everyFiveMinutes();

Schedule::command('billing:rollover')->dailyAt('00:10')->withoutOverlapping();
Schedule::command('billing:reconcile-redis')->dailyAt('04:00');
Schedule::command('billing:expire-trials')->hourly();

// Workflow engine triggers
Schedule::job(new PollTriggersJob)->everyMinute()->withoutOverlapping();
Schedule::job(new CheckScheduledTriggersJob)->everyMinute()->withoutOverlapping();

// Standalone agent scheduled triggers
Schedule::job(new CheckScheduledAgentTriggersJob)->everyMinute()->withoutOverlapping();

// Execution observability maintenance
Schedule::command('executions:archive-logs')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('executions:prune')->dailyAt('02:30')->withoutOverlapping();

// AI workflow builder cleanup
Schedule::command('workflow-builder:cleanup')->dailyAt('03:00')->withoutOverlapping();
