<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kill switch
    |--------------------------------------------------------------------------
    |
    | Turns every admin alert off without touching a single call site. Alerts
    | are raised from inside business paths (billing, usage), so this is the
    | one lever that guarantees a noisy deploy can be silenced immediately.
    */

    'enabled' => (bool) env('ADMIN_ALERTS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Recipients
    |--------------------------------------------------------------------------
    |
    | There are no admin user accounts: alerts go to fixed destinations read
    | from the environment, never to `User` models. Keeping routing in config
    | (rather than a table) means alerting still works during a database
    | incident, which is exactly when it matters.
    */

    'recipients' => [
        'mail' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ADMIN_ALERT_EMAILS', '')),
        ))),
        'discord' => env('ADMIN_ALERT_DISCORD_WEBHOOK'),
        'webhook' => env('ADMIN_ALERT_WEBHOOK_URL'),
    ],

    // Optional. When set, generic webhook deliveries carry an
    // `X-Admin-Alert-Signature: sha256=...` HMAC of the exact JSON body.
    'webhook_secret' => env('ADMIN_ALERT_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    |
    | Which destinations each severity reaches. A destination with no
    | configured recipient is skipped silently, so a half-configured
    | environment degrades instead of erroring.
    */

    'routing' => [
        'info' => ['discord'],
        'warning' => ['discord', 'webhook'],
        'critical' => ['discord', 'webhook', 'mail'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Throttling
    |--------------------------------------------------------------------------
    |
    | Admin alerts are raised by system conditions, not user actions, so the
    | same condition can re-trigger on every request. `AdminAlerts::raise()`
    | claims an atomic cache key per throttle key for this many seconds and
    | drops anything that arrives inside the window. Set to 0 to disable
    | (tests only, in production this is what stops one bad workspace from
    | flooding Discord).
    */

    'throttle_seconds' => (int) env('ADMIN_ALERT_THROTTLE_SECONDS', 3600),

    /*
    |--------------------------------------------------------------------------
    | Context redaction
    |--------------------------------------------------------------------------
    |
    | Alert context is rendered into Discord/webhook payloads that leave the
    | application, so any key whose name contains one of these fragments has
    | its value replaced before delivery. Matched case-insensitively, at every
    | depth of the array.
    */

    'redact_keys' => [
        'token',
        'secret',
        'password',
        'authorization',
        'api_key',
        'credential',
        'signature',
    ],

    /*
    |--------------------------------------------------------------------------
    | Usage alerts
    |--------------------------------------------------------------------------
    |
    | Percentage of a workspace's `usage_periods.credits_limit` that raises
    | `usage.threshold_crossed`. Only the deduction that crosses the line
    | alerts: see `DeductCreditsAction`.
    */

    'usage' => [
        'threshold_percent' => (int) env('ADMIN_ALERT_USAGE_THRESHOLD_PERCENT', 80),
    ],

];
