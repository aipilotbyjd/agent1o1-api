<?php

/**
 * Error tracking via GlitchTip (Sentry-compatible), through sentry/sentry-laravel.
 *
 * Reporting is off until GLITCHTIP_DSN is set. GlitchTip supports errors and basic
 * performance traces, but not profiling, so those stay disabled here.
 * Self-hosting: see deploy/glitchtip/.
 */
return [

    'dsn' => env('GLITCHTIP_DSN'),

    'release' => env('GLITCHTIP_RELEASE'),

    'environment' => env('GLITCHTIP_ENVIRONMENT', env('APP_ENV', 'production')),

    // Never attach IPs, cookies or user emails automatically.
    'send_default_pii' => false,

    // Error events only unless a sample rate is set (0.0 – 1.0).
    'traces_sample_rate' => env('GLITCHTIP_TRACES_SAMPLE_RATE') === null
        ? null
        : (float) env('GLITCHTIP_TRACES_SAMPLE_RATE'),

    'profiles_sample_rate' => null,

    'ignore_exceptions' => [
        Illuminate\Auth\AuthenticationException::class,
        Illuminate\Auth\Access\AuthorizationException::class,
        Illuminate\Validation\ValidationException::class,
        Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
    ],

    'breadcrumbs' => [
        'logs' => true,
        'cache' => false,
        'livewire' => false,
        'sql_queries' => false,
        'sql_bindings' => false,
        'queue_info' => true,
        'command_info' => true,
        'http_client_requests' => true,
    ],

    'tracing' => [
        'queue_job_transactions' => true,
        'queue_jobs' => true,
        'sql_queries' => false,
        'views' => false,
        'http_client_requests' => true,
    ],

];
