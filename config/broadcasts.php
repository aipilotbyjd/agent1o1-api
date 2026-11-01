<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Email campaign limits
    |--------------------------------------------------------------------------
    |
    | Guard rails for Super Admin email campaigns. A campaign above
    | `max_recipients` is refused outright, and `daily_limit` caps what the
    | whole platform may queue in a UTC day across every campaign. Mail leaves
    | at `rate_per_second` so a campaign never starves other queues of the
    | mail provider's quota.
    |
    */

    'max_recipients' => (int) env('BROADCAST_MAX_RECIPIENTS', 50000),

    'daily_limit' => (int) env('BROADCAST_DAILY_LIMIT', 100000),

    'rate_per_second' => (int) env('BROADCAST_RATE_PER_SECOND', 5),

    'chunk_size' => 500,

    // A `sending` campaign untouched this long is picked up again by
    // `broadcasts:dispatch-due`, which resumes it without re-mailing anyone.
    'stalled_after_minutes' => 10,

    // How many test sends one super admin may make per hour.
    'test_sends_per_hour' => 10,

    /*
    |--------------------------------------------------------------------------
    | Maintenance window
    |--------------------------------------------------------------------------
    |
    | While a published maintenance notice with `blocks_writes` is open, every
    | request that changes data is answered with 503, except the paths below
    | (Laravel `Request::is()` patterns). Signing in, billing and webhooks stay
    | up so people can still get in and pay, and the Super Admin API is never
    | blocked, so an operator can always end the window.
    |
    */

    'maintenance' => [
        'allow' => [
            'api/v1/auth/*',
            'api/v1/billing/*',
            'api/v1/announcements*',
            'api/v1/communication-preferences',
            'api/v1/broadcasts/*',
            'api/broadcasting/auth',
            // Inbound webhooks only queue work; refusing them would lose events from outside.
            'api/hooks/*',
            'api/stripe/*',
            'api/slack/*',
            'api/oauth/*',
        ],
    ],

];
