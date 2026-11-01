<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kill switch
    |--------------------------------------------------------------------------
    |
    | Silences every Super Admin alert without touching a call site. Kept
    | apart from `config/admin_alerts.php`, which covers billing and usage.
    |
    */

    'enabled' => (bool) env('SUPER_ADMIN_ALERTS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Recipients
    |--------------------------------------------------------------------------
    |
    | Fixed destinations read from the environment, never Super Admin models,
    | so alerting still works while the database is the problem.
    |
    */

    'recipients' => [
        'mail' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SUPER_ADMIN_ALERT_EMAILS', '')),
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Which events raise an alert.
    |
    */

    'events' => [
        'login_new_ip' => true,
        'locked_out' => true,
        'refresh_reuse_detected' => true,
        'ip_blocked' => true,
        'two_factor_reset' => true,
        'account_created' => true,
        'account_disabled' => true,
        'role_changed' => true,
        'spend_budget' => true,
        'spend_spike' => true,
        'provider_paused' => true,
        'audit_tampering' => true,
        'lockdown' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert engine
    |--------------------------------------------------------------------------
    |
    | How `alerts:evaluate` turns signals into notifications. Where they go is
    | decided by `config/admin_alerts.php` (Discord, webhook, mail by severity).
    |
    */

    'engine' => [
        // An unacknowledged alert is re-sent as critical after this long (0 turns escalation off).
        'escalate_after_minutes' => (int) env('SUPER_ADMIN_ALERT_ESCALATE_MINUTES', 60),
        // More notices than this in one evaluation are sent as a single digest.
        'digest_threshold' => 3,
        // Critical alerts ignore quiet hours.
        'quiet_hours' => [
            'enabled' => (bool) env('SUPER_ADMIN_ALERT_QUIET_HOURS', false),
            'start' => env('SUPER_ADMIN_ALERT_QUIET_START', '22:00'),
            'end' => env('SUPER_ADMIN_ALERT_QUIET_END', '07:00'),
            'timezone' => env('SUPER_ADMIN_ALERT_QUIET_TZ', 'UTC'),
        ],
        'keep_resolved_days' => 90,
    ],

];
