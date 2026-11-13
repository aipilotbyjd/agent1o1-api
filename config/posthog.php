<?php

/**
 * Product analytics via PostHog Cloud. Server-side events cover what the
 * browser can't see reliably (runs finishing, billing webhooks). Leave
 * POSTHOG_PROJECT_TOKEN empty to switch it off: nothing is queued or sent.
 *
 * Use the same project token the frontend uses (VITE_POSTHOG_PROJECT_TOKEN)
 * so browser and server events land on one person. For EU Cloud set
 * POSTHOG_HOST=https://eu.i.posthog.com.
 */
return [

    'enabled' => (bool) env('POSTHOG_ENABLED', true),

    'project_token' => env('POSTHOG_PROJECT_TOKEN'),

    'host' => env('POSTHOG_HOST', 'https://us.i.posthog.com'),

    // Seconds the HTTP call to PostHog may take before the queued job retries.
    'timeout' => (int) env('POSTHOG_TIMEOUT', 5),

];
