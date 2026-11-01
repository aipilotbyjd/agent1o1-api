<?php

/*
|--------------------------------------------------------------------------
| System health thresholds
|--------------------------------------------------------------------------
|
| Read by the checks behind `GET /system/health` and by the alert rules that
| watch them. Every value can be tuned in the environment without a deploy.
|
*/

return [

    // Per-check time budget, in seconds. A check that overruns is reported as down.
    'check_timeout' => (int) env('HEALTH_CHECK_TIMEOUT', 5),

    'scheduler' => [
        // The `system:heartbeat` command runs every minute: older than this means the scheduler is dead.
        'heartbeat_stale_seconds' => (int) env('HEALTH_SCHEDULER_STALE_SECONDS', 180),
        // A command is overdue once it has not started for this many times its expected interval.
        'overdue_factor' => (float) env('HEALTH_SCHEDULER_OVERDUE_FACTOR', 2.5),
        'overdue_grace_seconds' => 120,
        'keep_runs_days' => 7,
    ],

    'queues' => [
        // Seconds the oldest waiting job may have waited before a queue is degraded, and then down.
        'wait_warning_seconds' => (int) env('HEALTH_QUEUE_WAIT_WARNING', 300),
        'wait_critical_seconds' => (int) env('HEALTH_QUEUE_WAIT_CRITICAL', 900),
        // Per-queue overrides, e.g. 'ai-agent' => ['warning' => 60, 'critical' => 180].
        'overrides' => [],
        'failed_jobs_warning' => (int) env('HEALTH_FAILED_JOBS_WARNING', 25),
        'failed_jobs_critical' => (int) env('HEALTH_FAILED_JOBS_CRITICAL', 200),
    ],

    'disk' => [
        'path' => storage_path(),
        'free_percent_warning' => 15,
        'free_percent_critical' => 5,
    ],

    'stuck' => [
        'runs_after_minutes' => (int) env('HEALTH_STUCK_RUNS_MINUTES', 60),
        'trigger_events_after_minutes' => (int) env('HEALTH_STUCK_EVENTS_MINUTES', 15),
        'warning_count' => 1,
        'critical_count' => 25,
    ],

    'snapshots' => [
        'keep_days' => 30,
    ],

    // Bulk failed-job actions never touch more than this many jobs in one call.
    'bulk_retry_limit' => 500,

    // Payload keys whose values are replaced before a failed job is shown.
    'redact_keys' => ['password', 'secret', 'token', 'authorization', 'api_key', 'apikey', 'key', 'credential', 'cookie', 'signature'],

];
