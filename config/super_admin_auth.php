<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Token lifetimes
    |--------------------------------------------------------------------------
    |
    | Access tokens are short; a refresh rotates both tokens. A session can
    | never outlive "session_absolute_days" however often it is refreshed.
    | A "setup" token is what a super admin without confirmed two-factor
    | authentication receives: it only opens the setup endpoints.
    |
    */

    'access_token_minutes' => (int) env('SUPER_ADMIN_ACCESS_TOKEN_MINUTES', 15),

    'refresh_token_days' => (int) env('SUPER_ADMIN_REFRESH_TOKEN_DAYS', 7),

    'session_absolute_days' => (int) env('SUPER_ADMIN_SESSION_ABSOLUTE_DAYS', 30),

    'setup_token_minutes' => 30,

    /*
    |--------------------------------------------------------------------------
    | Two-factor challenge
    |--------------------------------------------------------------------------
    */

    'challenge_minutes' => 5,

    'max_two_factor_attempts' => 5,

    /*
    |--------------------------------------------------------------------------
    | Login lockout
    |--------------------------------------------------------------------------
    |
    | An account locks every "max_attempts" consecutive failures. "backoff"
    | lists the lock in minutes for the first, second, third (and so on)
    | lockout; the last entry repeats. A successful sign-in resets the count.
    |
    */

    'lockout' => [
        'max_attempts' => 5,
        'backoff' => [15, 30, 60, 240],
    ],

    /*
    |--------------------------------------------------------------------------
    | Passwords
    |--------------------------------------------------------------------------
    |
    | A password older than "rotation_days" (or never changed since the account
    | was provisioned) blocks everything except changing it.
    |
    */

    'password' => [
        'min_length' => 14,
        'rotation_days' => (int) env('SUPER_ADMIN_PASSWORD_ROTATION_DAYS', 90),
        'check_compromised' => (bool) env('SUPER_ADMIN_CHECK_COMPROMISED', env('APP_ENV') !== 'testing'),
    ],

];
