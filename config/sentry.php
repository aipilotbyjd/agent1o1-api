<?php

/**
 * Error tracking via the Sentry SDK, pointed at GlitchTip (Sentry-compatible).
 *
 * Switching from GlitchTip Cloud to a self-hosted instance only needs a new
 * SENTRY_LARAVEL_DSN; nothing else here changes. Leave the DSN empty to disable.
 */
return [

    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    'release' => env('SENTRY_RELEASE'),

    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),

    // Never send user IPs, cookies or request bodies unless explicitly enabled.
    'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', false),

    'sample_rate' => (float) env('SENTRY_SAMPLE_RATE', 1.0),

    // Performance tracing is optional on GlitchTip; keep it low or null.
    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE') === null ? null : (float) env('SENTRY_TRACES_SAMPLE_RATE'),

    'breadcrumbs' => [
        'logs' => true,
        'cache' => true,
        'livewire' => false,
        'sql_queries' => true,
        'sql_bindings' => false,
        'queue_info' => true,
        'command_info' => true,
        'http_client_requests' => true,
    ],

    'tracing' => [
        'queue_job_transactions' => env('SENTRY_TRACE_QUEUE_ENABLED', false),
        'queue_jobs' => true,
        'sql_queries' => true,
        'sql_origin' => true,
        'views' => true,
        'http_client_requests' => true,
        'default_integrations' => true,
    ],

];
