<?php

/*
|--------------------------------------------------------------------------
| Operations checklist
|--------------------------------------------------------------------------
|
| The recurring setup, daily, weekly and monthly tasks shown in the admin
| panel, and the limits the automatic checks use to decide between "all
| clear", "look at this" and "act now". Times are UTC.
|
*/

return [

    /*
    | When the morning reminder goes out (HH:MM, UTC). It is sent through the
    | admin alert channels in config/admin_alerts.php, and only when
    | something needs attention.
    */
    'digest_at' => env('OPERATIONS_DIGEST_AT', '08:00'),

    /*
    | The snooze lengths on offer, in hours.
    */
    'snooze_hours' => [24, 72, 168],

    /*
    | The admin pages a task can point to. The panel turns each into a link.
    */
    'targets' => [
        'dashboard', 'runs', 'system', 'maintenance', 'backups', 'alerts', 'billing-events', 'audit',
        'admin-team', 'revenue', 'unit-economics', 'recommendations', 'referrals', 'catalog',
        'broadcasts', 'ai-models', 'pricing',
    ],

    /*
    | Limits for the automatic checks.
    */
    'checks' => [
        'scheduler_warn_seconds' => 180,
        'scheduler_fail_seconds' => 600,
        'health_max_age_minutes' => 15,
        'failed_jobs_fail_at' => 25,
        'stuck_work_fail_at' => 10,
        'webhook_fail_after_hours' => 24,
        'backups_max_age_hours' => 26,
    ],

];
