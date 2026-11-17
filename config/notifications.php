<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | How long inbox notifications and delivery records are kept. Read
    | notifications go sooner than unread ones; nothing in the inbox lives
    | longer than `unread_days`. Pruned daily by `model:prune`.
    |
    */

    'retention' => [
        'read_days' => (int) env('NOTIFICATIONS_READ_RETENTION_DAYS', 90),
        'unread_days' => (int) env('NOTIFICATIONS_UNREAD_RETENTION_DAYS', 180),
        'delivery_days' => (int) env('NOTIFICATIONS_DELIVERY_RETENTION_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    |
    | A notification that names an idempotency key (a Stripe invoice, a run)
    | is delivered once per recipient and channel within this window, however
    | many times its trigger fires: Stripe retries, a re-run job, and so on.
    |
    */

    'idempotency_hours' => 48,

    /*
    |--------------------------------------------------------------------------
    | Daily digest
    |--------------------------------------------------------------------------
    |
    | Emails a member chose to receive as a digest are collected and sent
    | together once a day at this UTC time by `notifications:send-digests`.
    |
    */

    'digest' => [
        'send_at' => env('NOTIFICATIONS_DIGEST_SEND_AT', '07:10'),
        'max_items' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Chat and webhook channels
    |--------------------------------------------------------------------------
    |
    | A channel that fails this many deliveries in a row (after retries) is
    | switched off and the workspace's admins are told. 0 never switches off.
    |
    */

    'integrations' => [
        'disable_after_failures' => (int) env('NOTIFICATIONS_DISABLE_CHANNEL_AFTER_FAILURES', 10),
        'signature_header' => 'X-Notification-Signature',
        'timestamp_header' => 'X-Notification-Timestamp',
        'payload_version' => 1,
    ],

];
