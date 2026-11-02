<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin Intelligence
    |--------------------------------------------------------------------------
    |
    | Settings for the analysis, forecasting and recommendation layer the
    | Super Admin reads (`App\Services\Intelligence`).
    |
    | `rollup_days`: how many of the latest days `intelligence:rollup`
    | rebuilds each hour (backfill with `--days=`).
    |
    | `abuse.free_spend_usd`: provider cost over three days, without paying,
    | that starts to count towards a workspace's abuse score.
    |
    | `recommendations`: the thresholds the rules use. `thin_margin_percent`
    | flags a plan or model whose gross margin falls under it;
    | `failure_rate` a route whose share of failed calls rises above it;
    | `cache_hit_rate` a heavy model whose prompt cache serves less than it;
    | `internal_feature_usd` an internal feature (judges, builder, drafting)
    | that spent more than this in a week on a premium model.
    |
    | `analyst`: the model the admin analyst runs on (a catalog slug, or null
    | for the workspace-free default provider) and how many tool calls one
    | question may make.
    |
    | `briefing`: who gets the weekly briefing (defaults to the Super Admin
    | alert recipients) and whether it is sent.
    |
    */

    'rollup_days' => (int) env('INTELLIGENCE_ROLLUP_DAYS', 2),

    'abuse' => [
        'free_spend_usd' => (float) env('INTELLIGENCE_ABUSE_FREE_SPEND_USD', 2),
    ],

    'recommendations' => [
        'thin_margin_percent' => (float) env('INTELLIGENCE_THIN_MARGIN_PERCENT', 20),
        'failure_rate' => (float) env('INTELLIGENCE_FAILURE_RATE', 0.05),
        'cache_hit_rate' => (float) env('INTELLIGENCE_CACHE_HIT_RATE', 0.1),
        'internal_feature_usd' => (float) env('INTELLIGENCE_INTERNAL_FEATURE_USD', 20),
        'loss_workspace_usd' => (float) env('INTELLIGENCE_LOSS_WORKSPACE_USD', 10),
    ],

    'analyst' => [
        'model' => env('INTELLIGENCE_ANALYST_MODEL'),
        'max_steps' => (int) env('INTELLIGENCE_ANALYST_MAX_STEPS', 8),
    ],

    'briefing' => [
        'enabled' => (bool) env('INTELLIGENCE_BRIEFING_ENABLED', true),
        'recipients' => array_values(array_filter(array_map('trim', explode(',', (string) env('INTELLIGENCE_BRIEFING_EMAILS', ''))))),
    ],

];
