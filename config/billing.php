<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Plan
    |--------------------------------------------------------------------------
    |
    | The plan a workspace falls back to when it has no *valid* subscription,
    | either it never subscribed, or its subscription lapsed (canceled, past
    | due, incomplete). Without this every unsubscribed workspace resolved to
    | a `null` plan, which `UsagePeriod::hasRemainingCredits()` treats as an
    | unlimited allowance. Resolved by `Plan::default()`; a missing/inactive
    | slug still yields `null` (unlimited) so a half-seeded install fails open
    | rather than locking every workspace out.
    |
    */

    'default_plan' => env('BILLING_DEFAULT_PLAN', 'free'),

    /*
    |--------------------------------------------------------------------------
    | Free Credits, Trials and Abuse Checks
    |--------------------------------------------------------------------------
    |
    | Who receives free and trial credits. Every key here can be overridden
    | from Super Admin (`BillingSettings::POLICY_KEYS`) without a deploy.
    |
    | `free_credits.one_per_owner`: only the owner's first workspace
    | (`users.free_workspace_id`) gets the free plan's monthly credits; any
    | other free workspace starts at 0 and works on top-ups or a paid plan.
    |
    | `abuse.enforcement`: `enforce` applies the checks below; `shadow` runs
    | them and records what they would have denied without denying it, so
    | they can be tuned on real traffic first; `off` skips them.
    |
    */

    'free_credits' => [
        'one_per_owner' => (bool) env('BILLING_FREE_CREDITS_ONE_PER_OWNER', true),
        'require_verified_email' => (bool) env('BILLING_FREE_CREDITS_REQUIRE_VERIFIED_EMAIL', true),
    ],

    'trials' => [
        'one_per_user' => (bool) env('BILLING_TRIALS_ONE_PER_USER', true),
        'one_per_card' => (bool) env('BILLING_TRIALS_ONE_PER_CARD', true),
        'block_prepaid_cards' => (bool) env('BILLING_TRIALS_BLOCK_PREPAID_CARDS', true),
    ],

    'abuse' => [
        'enforcement' => env('FREE_CREDIT_ENFORCEMENT', 'enforce'),
        'max_free_claims_per_network_per_day' => (int) env('BILLING_MAX_FREE_CLAIMS_PER_NETWORK_PER_DAY', 3),
        'block_disposable_email' => (bool) env('BILLING_BLOCK_DISPOSABLE_EMAIL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Cost Guardrails
    |--------------------------------------------------------------------------
    |
    | Dollar limits on model cost paid on platform keys. Per-plan limits live
    | on the plan (`limits.ai_cost_daily_usd`, `ai_cost_weekly_usd`,
    | `max_run_credits`, `max_concurrent_runs`, `features.model_tiers`); this
    | is the platform-wide breaker: once free and trial workspaces together
    | pass `free_tier_daily_budget_usd` in a UTC day, their new runs pause
    | until the next day. Paying workspaces are never paused by it. `null`
    | turns the breaker off.
    |
    */

    'guardrails' => [
        'enabled' => (bool) env('BILLING_GUARDRAILS_ENABLED', true),
        'free_tier_daily_budget_usd' => env('FREE_TIER_DAILY_BUDGET_USD') === null ? null : (float) env('FREE_TIER_DAILY_BUDGET_USD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trial Ending Warning
    |--------------------------------------------------------------------------
    |
    | How many days before `trial_ends_at` `billing:notify-trial-ending` warns
    | a workspace that its trial is about to convert to a paid charge.
    |
    */

    'trial_ending_warning_days' => (int) env('BILLING_TRIAL_ENDING_WARNING_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Billing Intervals
    |--------------------------------------------------------------------------
    |
    | Estate-wide kill switch per billing window. Turning one off withdraws it
    | from sale across every plan at once: use it to pull `lifetime` from the
    | whole catalog without editing each plan's `enabled_intervals`.
    |
    | This only blocks *new* purchases. Workspaces already entitled by a
    | subscription or a plan grant on a disabled interval keep what they paid
    | for; see `Plan::isSoldOn()`.
    |
    */

    'intervals' => [
        'monthly' => (bool) env('BILLING_INTERVAL_MONTHLY_ENABLED', true),
        'quarterly' => (bool) env('BILLING_INTERVAL_QUARTERLY_ENABLED', true),
        'yearly' => (bool) env('BILLING_INTERVAL_YEARLY_ENABLED', true),
        'lifetime' => (bool) env('BILLING_INTERVAL_LIFETIME_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit Packs
    |--------------------------------------------------------------------------
    |
    | One-time credit top-ups, purchasable via Stripe Checkout regardless of
    | Stripe price/product setup: `checkoutCharge()` builds the Checkout
    | Session from `price_cents` directly, so no Stripe Dashboard config is
    | needed to add or resize a pack. Purchased credits land in the
    | workspace's non-expiring `topup_credits` pool, not the current usage
    | period: see `ActivateCreditPackAction`.
    |
    */

    'packs' => [
        'small' => [
            'label' => '1,000 credits',
            'credits' => 1000,
            'price_cents' => 900,
        ],
        'medium' => [
            'label' => '5,000 credits',
            'credits' => 5000,
            'price_cents' => 3900,
        ],
        'large' => [
            'label' => '20,000 credits',
            'credits' => 20000,
            'price_cents' => 12900,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit Overage
    |--------------------------------------------------------------------------
    |
    | Gumloop's "enable credit overage to keep running past your monthly
    | credits, billed at $0.005 per credit". Off per workspace until it opts
    | in (`workspaces.credit_overage_enabled`) and only offered on plans
    | carrying the `credit_overage` feature.
    |
    | `default_limit` is the ceiling on how many overage credits one billing
    | period may accrue when a workspace hasn't set its own: Gumloop's
    | "default ceiling is 1,000,000 overage credits per billing period
    | ($5,000)". A workspace may lower this on its subscription screen but
    | never raise it, so overage can't run away unbounded. Setting the env var
    | to `null` lifts the ceiling estate-wide, which is Gumloop's uncapped
    | Enterprise case.
    |
    | `minimum_invoice_cents` stops `billing:invoice-overage` opening a Stripe
    | invoice for a few cents of accrued usage: anything below it rolls into
    | the next run rather than being written off, since the credits stay
    | unbilled.
    |
    */

    'overage' => [
        'default_limit' => is_numeric(env('BILLING_OVERAGE_DEFAULT_LIMIT', 1_000_000))
            ? (int) env('BILLING_OVERAGE_DEFAULT_LIMIT', 1_000_000)
            : null,
        'minimum_invoice_cents' => (int) env('BILLING_OVERAGE_MINIMUM_INVOICE_CENTS', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit Notifications
    |--------------------------------------------------------------------------
    |
    | Gumloop's Credit Notification Preferences: "Credit Usage Notifications
    | ... defaults to 75% and 90%". These are the thresholds a workspace gets
    | until it sets its own on `PUT /billing/credit-notifications`; each is a
    | percentage of the period's `credits_limit`, and only the charge that
    | actually crosses one notifies.
    |
    | `maximum_thresholds` caps how many a workspace may configure, so a
    | threshold per percent can't turn one busy period into 100 emails.
    |
    | This is distinct from `admin_alerts.usage.threshold_percent`, which is
    | the operator-facing alert and stays a single estate-wide number.
    |
    */

    'credit_notifications' => [
        'default_thresholds' => [75, 90],
        'maximum_thresholds' => (int) env('BILLING_CREDIT_NOTIFICATION_MAX_THRESHOLDS', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Node Credit Costs
    |--------------------------------------------------------------------------
    |
    | Flat credits a workflow node's type adds on top of `CreditMeter`'s base
    | 1 credit per run, keyed by the node's `type()` string (matches
    | `workflow_nodes.type` / `NodeRun.type`). A type left out of this table
    | costs nothing extra: most nodes (transform, filter, Slack, Gmail,
    | GitHub, Google Suite, ...) are free beyond the base credit, same as
    | Gumloop's node pricing.
    |
    | `run_code` is priced here because it executes in an isolated sandbox
    | for security, which carries a real infra cost: the same bracket as
    | Gumloop's "Custom and MCP nodes: 3 credits each". `agent` matches
    | Gumloop's own stated Agent-node price: "a base cost of 3 credits per
    | run, charged on top of the actual credit cost of running the agent"
    | (docs/gumloop/output/raw/core-concepts/agent_node.md's "Credit Costs"
    | section): `CreditMeter::costForNodeRun()` already adds this on top of
    | the node's own token cost. Add an entry here for any future node type
    | that deserves its own surcharge (a real custom/MCP node, image
    | generation, data enrichment, ...) instead of changing `CreditMeter`.
    |
    */

    'node_costs' => [
        'run_code' => (int) env('BILLING_NODE_COST_RUN_CODE', 3),
        'agent' => (int) env('BILLING_NODE_COST_AGENT', 3),
        'ai_image' => (int) env('BILLING_NODE_COST_AI_IMAGE', 10),
        'ai_speech' => (int) env('BILLING_NODE_COST_AI_SPEECH', 3),
        'ai_transcribe' => (int) env('BILLING_NODE_COST_AI_TRANSCRIBE', 3),
        'web_scrape' => (int) env('BILLING_NODE_COST_WEB_SCRAPE', 1),
        'page_to_markdown' => (int) env('BILLING_NODE_COST_PAGE_TO_MARKDOWN', 1),
        'brave_search_web' => (int) env('BILLING_NODE_COST_SEARCH', 1),
        'brave_search_news' => (int) env('BILLING_NODE_COST_SEARCH', 1),
        'tavily_search' => (int) env('BILLING_NODE_COST_SEARCH', 1),
        'tavily_extract' => (int) env('BILLING_NODE_COST_SEARCH_EXTRACT', 2),
        'hunter_domain_search' => (int) env('BILLING_NODE_COST_ENRICHMENT', 2),
        'hunter_find_email' => (int) env('BILLING_NODE_COST_ENRICHMENT', 2),
        'hunter_verify_email' => (int) env('BILLING_NODE_COST_ENRICHMENT', 2),
        'hunter_find_company' => (int) env('BILLING_NODE_COST_ENRICHMENT', 2),
        'apollo_enrich_person' => (int) env('BILLING_NODE_COST_ENRICHMENT', 2),
        'apollo_enrich_company' => (int) env('BILLING_NODE_COST_ENRICHMENT', 2),
        'google_maps_search_places' => (int) env('BILLING_NODE_COST_MAPS', 1),
        'google_maps_get_place' => (int) env('BILLING_NODE_COST_MAPS', 1),
        'google_maps_geocode' => (int) env('BILLING_NODE_COST_MAPS', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit Value
    |--------------------------------------------------------------------------
    |
    | What one credit is worth in USD: mirrors Gumloop's "$1 buys 200
    | credits" ($0.005/credit). `CreditMeter` divides a model call's real
    | dollar cost (from `model_prices` below) by this to get its credit
    | price, rounded up.
    |
    */

    'credit_value_usd' => (float) env('BILLING_CREDIT_VALUE_USD', 0.005),

    /*
    |--------------------------------------------------------------------------
    | Orchestration Fee
    |--------------------------------------------------------------------------
    |
    | Gumloop's "running the agent loop itself" surcharge on agent chats:
    | applied by `CreditMeter` to the sum of an agent turn's reasoning, tool
    | call, and compute credits. Workflow runs never carry this fee; see
    | Gumloop's "Compute and the orchestration fee apply to agent chats
    | only."
    |
    */

    'orchestration_fee_rate' => (float) env('BILLING_ORCHESTRATION_FEE_RATE', 0.08),

    /*
    |--------------------------------------------------------------------------
    | Model Prices
    |--------------------------------------------------------------------------
    |
    | These prices are now maintained in the `model_prices` table (see
    | `ModelPrices`, and the Super Admin API under /models/prices); a price
    | there wins over the same key here. This map is the fallback for any
    | model not in the table yet: `php artisan model-prices:import-config`
    | copies it across, after which it can be emptied.
    |
    | Real $ per 1M tokens, keyed by "{provider}:{model}": the same
    | provider/model strings `laravel/ai` reports back on a response's
    | `Meta` (and that match `model_routes.execution_provider`/
    | `execution_model_id`). `CreditMeter` uses this to bill a model call at
    | its real cost (`credit_value_usd` above), the same way Gumloop's
    | "Model calls bill at cost" works.
    |
    | This app's `ModelCatalogDataset` lists a mostly fictional, future-dated
    | model lineup (Claude Opus 5, GPT-5.6 *, Gemini 3.x, Grok 4.6, DeepSeek
    | V4, Kimi K3, GLM-5.2, MiniMax M3, Qwen3.6, Mistral Medium 3.5, ...):
    | none of those exist yet, so there's no real price to put here, and this
    | table deliberately does not guess one. Every unpriced model (the
    | fictional ones, plus any real one not yet added below) falls back to
    | `CreditMeter`'s flat per-1,000-token ratio rather than being charged 0
    | credits.
    |
    | The rates below are best-effort, not independently verified this
    | session against each vendor's current pricing page: treat them as a
    | starting point and confirm against the vendor before relying on them
    | for real billing, the same way `ModelCatalogDataset` flags its own
    | best-effort aggregator model ids. Add a row here once a model's price
    | is confirmed.
    |
    */

    'model_prices' => [
        // OpenAI: unverified, confirm against OpenAI's current pricing.
        'openai:gpt-4o' => ['input' => 2.50, 'output' => 10.00],
        'openai:gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],

        // OpenAI embeddings: unverified, confirm against OpenAI's current
        // pricing. Input-only; `text-embedding-3-small` is `laravel/ai`'s
        // default embeddings model, which `KnowledgeBase::ingest()` bills.
        'openai:text-embedding-3-small' => ['input' => 0.02, 'output' => 0.00],
        'openai:text-embedding-3-large' => ['input' => 0.13, 'output' => 0.00],

        'gemini:gemini-embedding-2' => ['input' => 0.15, 'output' => 0.00],

        // Anthropic: unverified, confirm against Anthropic's current
        // pricing. Keyed to Claude Haiku 4.5's primary route
        // (`ModelCatalogDataset`'s enabled anthropic route).
        'anthropic:claude-haiku-4-5-20251001' => ['input' => 1.00, 'output' => 5.00],

        // Fireworks: unverified, confirm against Fireworks' current
        // pricing for Meta's Llama 3.1 405B. Every route for this catalog
        // entry ships disabled by default (aggregator-only, no direct Meta
        // API); keyed to Fireworks' model id since that's the seeded
        // priority-0 route: re-key this if a different route (e.g.
        // Together) becomes primary instead.
        'fireworks:accounts/fireworks/models/llama-v3p1-405b-instruct' => ['input' => 3.00, 'output' => 3.00],
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe IDs for the starting plans
    |--------------------------------------------------------------------------
    |
    | Read by the `plans` setup dataset when it first creates a plan. They live
    | here because `env()` returns null once the config is cached.
    */

    'stripe_plan_ids' => [
        'STRIPE_PLAN_PRO_LIFETIME_PRICE_ID' => env('STRIPE_PLAN_PRO_LIFETIME_PRICE_ID'),
        'STRIPE_PLAN_PRO_MONTHLY_PRICE_ID' => env('STRIPE_PLAN_PRO_MONTHLY_PRICE_ID'),
        'STRIPE_PLAN_PRO_PRODUCT_ID' => env('STRIPE_PLAN_PRO_PRODUCT_ID'),
        'STRIPE_PLAN_PRO_QUARTERLY_PRICE_ID' => env('STRIPE_PLAN_PRO_QUARTERLY_PRICE_ID'),
        'STRIPE_PLAN_PRO_YEARLY_PRICE_ID' => env('STRIPE_PLAN_PRO_YEARLY_PRICE_ID'),
        'STRIPE_PLAN_STARTER_LIFETIME_PRICE_ID' => env('STRIPE_PLAN_STARTER_LIFETIME_PRICE_ID'),
        'STRIPE_PLAN_STARTER_MONTHLY_PRICE_ID' => env('STRIPE_PLAN_STARTER_MONTHLY_PRICE_ID'),
        'STRIPE_PLAN_STARTER_PRODUCT_ID' => env('STRIPE_PLAN_STARTER_PRODUCT_ID'),
        'STRIPE_PLAN_STARTER_QUARTERLY_PRICE_ID' => env('STRIPE_PLAN_STARTER_QUARTERLY_PRICE_ID'),
        'STRIPE_PLAN_STARTER_YEARLY_PRICE_ID' => env('STRIPE_PLAN_STARTER_YEARLY_PRICE_ID'),
    ],

];
