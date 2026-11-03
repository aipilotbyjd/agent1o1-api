<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Usage rollup
    |--------------------------------------------------------------------------
    |
    | `model-usage:rollup` rebuilds the per-day, per-workspace, per-model usage
    | table from the usage records of node runs and agent messages. It rebuilds
    | this many of the latest days each time (today and yesterday by default):
    | a record's usage is written when its call finishes, which can be after the
    | day it was created, so recent days are recomputed rather than appended to.
    | Older days are left as they were; backfill with `--days=31`.
    |
    */

    'rollup_days' => (int) env('MODEL_USAGE_ROLLUP_DAYS', 2),

    /*
    |--------------------------------------------------------------------------
    | Spend guard
    |--------------------------------------------------------------------------
    |
    | Alerts, never blocks: a spike is as likely to be a good customer as an
    | abuser, so the guard tells you and leaves the decision (suspend, cap,
    | ignore) to you. All amounts are US dollars of *provider cost* (what the
    | platform pays), estimated from the model prices; calls on a workspace's own
    | key cost you nothing and are not counted. A model with no price cannot be
    | counted either, which the health report says.
    |
    | "auto_throttle" pauses a free or trial workspace that spikes until the
    | end of the day; a paying one is only reported.
    |
    | "daily_usd" is a budget for the whole platform for one day. You are warned
    | at "warn_ratio" of it and again when it is passed. Null switches it off.
    |
    | "workspace.daily_usd" is the most any one workspace should cost in a day.
    | "spike_factor" flags a workspace whose spend today is that many times its
    | daily average over the previous seven days, once it has also passed
    | "spike_min_usd" (so a workspace going from 10 cents to 80 is not a spike).
    |
    */

    'budget' => [
        'daily_usd' => env('MODEL_DAILY_BUDGET_USD') === null ? null : (float) env('MODEL_DAILY_BUDGET_USD'),
        'warn_ratio' => (float) env('MODEL_BUDGET_WARN_RATIO', 0.8),
    ],

    'workspace' => [
        'daily_usd' => env('MODEL_WORKSPACE_DAILY_USD') === null ? null : (float) env('MODEL_WORKSPACE_DAILY_USD'),
        'spike_factor' => (float) env('MODEL_WORKSPACE_SPIKE_FACTOR', 5),
        'spike_min_usd' => (float) env('MODEL_WORKSPACE_SPIKE_MIN_USD', 5),
        'auto_throttle' => (bool) env('MODEL_WORKSPACE_AUTO_THROTTLE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI call ledger
    |--------------------------------------------------------------------------
    |
    | Every request sent to an AI provider is written to `ai_call_events` with
    | what it cost the platform (see `AiCallRecorder`). It is the source for
    | the unit-economics reports, the real-dollar cost caps and the forecasts.
    | `ai-calls:prune` deletes rows older than `retention_days`: the daily
    | facts (`fact_usage_daily`) keep the history after that.
    |
    */

    'call_ledger' => [
        'enabled' => (bool) env('AI_CALL_LEDGER_ENABLED', true),
        'retention_days' => (int) env('AI_CALL_LEDGER_RETENTION_DAYS', 180),
    ],

    /*
    |--------------------------------------------------------------------------
    | Price sync
    |--------------------------------------------------------------------------
    |
    | `model-prices:sync` reads a public list of model prices (OpenRouter's by
    | default) to fill in missing, demo and unverified prices as `unverified`,
    | and to raise `model_price_drift` when a verified price no longer matches
    | the list. It never changes a verified price.
    |
    */

    'price_sync' => [
        'enabled' => (bool) env('MODEL_PRICE_SYNC_ENABLED', true),
        'feed_url' => env('MODEL_PRICE_SYNC_FEED_URL', 'https://openrouter.ai/api/v1/models'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider SLOs
    |--------------------------------------------------------------------------
    |
    | What a healthy provider route looks like over the last hour, for the
    | live operations board and the recommendation that a route be moved
    | down: the share of calls that succeed, and the 95th percentile latency.
    |
    */

    'slo' => [
        'success_rate' => (float) env('MODEL_SLO_SUCCESS_RATE', 0.99),
        'p95_latency_ms' => (int) env('MODEL_SLO_P95_LATENCY_MS', 20000),
    ],

];
