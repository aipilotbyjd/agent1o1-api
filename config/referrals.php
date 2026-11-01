<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kill switch
    |--------------------------------------------------------------------------
    |
    | Everything else about the referral program: rewards, triggers, caps,
    | holds, fraud checks: lives in the database and is edited from the
    | admin API. This is the one setting kept in the environment: it stops
    | all new attribution and every new reward immediately, even when the
    | database is the problem.
    */

    'enabled' => (bool) env('REFERRALS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Settings cache
    |--------------------------------------------------------------------------
    |
    | Programs, rules and blocked domains are read on hot paths (signups,
    | webhooks, every completed run) and cached for this many seconds. Every
    | admin change clears the cache, so this only bounds staleness if a row
    | is edited outside Eloquent.
    */

    'cache_ttl_seconds' => (int) env('REFERRALS_CACHE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Visit retention
    |--------------------------------------------------------------------------
    |
    | Days raw `?ref=` visit rows are kept before `referrals:prune-visits`
    | deletes them. Referrals keep a link to their visit until then.
    */

    'visit_retention_days' => (int) env('REFERRALS_VISIT_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Plan-time reminder
    |--------------------------------------------------------------------------
    |
    | How many days before earned referral plan time runs out the workspace
    | is reminded, by `referrals:notify-plan-time-ending`.
    */

    'plan_time_ending_notice_days' => (int) env('REFERRALS_PLAN_TIME_NOTICE_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Shared mail providers
    |--------------------------------------------------------------------------
    |
    | Domains the `same_email_domain` fraud check ignores: two strangers
    | both on gmail.com are not evidence of anything.
    */

    'free_email_domains' => [
        'gmail.com', 'googlemail.com', 'outlook.com', 'hotmail.com', 'live.com',
        'yahoo.com', 'icloud.com', 'me.com', 'proton.me', 'protonmail.com',
        'aol.com', 'gmx.com', 'mail.com', 'zoho.com', 'yandex.com',
    ],

];
