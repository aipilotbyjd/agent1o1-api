<?php

/*
|--------------------------------------------------------------------------
| Code sandboxes
|--------------------------------------------------------------------------
|
| User and AI code never runs on this server: every provider is a remote
| service (a hosted sandbox, or a runner on a separate machine). Which
| providers exist, their credentials and their order are managed in the
| Super Admin panel and kept in `sandbox_providers`; this file only holds
| the tuning that is safe to keep in code. `E2B_API_KEY` (see
| `config/assistant.php`) still works as a fallback provider until one is
| added in the panel.
|
*/

return [

    /*
    | A provider that fails this many calls in a row is skipped for the
    | cooldown, then tried again with the next call.
    */
    'circuit' => [
        'failures' => (int) env('SANDBOX_CIRCUIT_FAILURES', 3),
        'cooldown_seconds' => (int) env('SANDBOX_CIRCUIT_COOLDOWN_SECONDS', 120),
    ],

    /*
    | Workflow "Code" node limits. The daily cap is per workspace and counts
    | seconds of sandbox time; null removes it.
    */
    'code' => [
        'default_timeout_seconds' => (int) env('SANDBOX_CODE_TIMEOUT_SECONDS', 30),
        'max_timeout_seconds' => (int) env('SANDBOX_CODE_MAX_TIMEOUT_SECONDS', 120),
        'max_code_kilobytes' => 64,
        'max_input_kilobytes' => 256,
        'max_output_chars' => 20000,
        'workspace_daily_seconds' => env('SANDBOX_WORKSPACE_DAILY_SECONDS', 3600) === null ? null : (int) env('SANDBOX_WORKSPACE_DAILY_SECONDS', 3600),
    ],

    /*
    | How long a one-shot run may hold a provider slot before the slot is
    | released on its own (a crashed worker must not block the provider).
    */
    'slot_ttl_seconds' => 300,

    /*
    | A computer idle this long past its idle timeout is deleted at the
    | provider by `sandbox:cleanup`, in case the provider did not.
    */
    'cleanup_grace_seconds' => 300,

    /*
    | Hosts of sandbox providers are checked like operator AI endpoints
    | (`EndpointUrlGuard`): https, public addresses only.
    */
];
