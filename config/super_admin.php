<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | The one and only on/off switch for the whole Super Admin panel. Off (the
    | default), no Super Admin route is registered, the `super-admin` guard
    | rejects every token, and every `super-admin:*` command refuses to run.
    | The tables stay, so switching it back on loses nothing.
    |
    | Always read through `config()`, never `env()` in code, so it keeps
    | working under `config:cache`.
    |
    */

    'enabled' => (bool) env('SUPER_ADMIN_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | URL prefix and host
    |--------------------------------------------------------------------------
    |
    | Routes live under `/api/{prefix}`. Setting a host serves them only on
    | that domain (e.g. an admin-only deployment hostname); leave it empty to
    | answer on every host the application is reachable on.
    |
    */

    'prefix' => 'super-admin/v1',

    'host' => env('SUPER_ADMIN_HOST'),

];
