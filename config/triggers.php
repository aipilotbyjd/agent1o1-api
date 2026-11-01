<?php

/*
|--------------------------------------------------------------------------
| Workflow & agent triggers
|--------------------------------------------------------------------------
|
| Everything that starts runs automatically: schedules, webhooks, polling
| (plain URLs and app readers switched on as flow triggers) and manual
| "Run now". See docs/TRIGGERS_PLAN.md for the pipeline. Plan-level caps
| (how many active triggers a workspace may have) are not here: they're the
| `active_triggers` key of each plan's `limits` (PlanLimit::ActiveTriggers).
|
| The assistant's own triggers are configured in config/assistant.php.
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Rollout
    |--------------------------------------------------------------------------
    |
    | Off hides the trigger nodes, the app triggers in Triggers & Events and
    | the "Activate as flow trigger" switch from the node catalog, so the
    | editor can't add new ones. Triggers already published keep running.
    */

    'nodes_enabled' => (bool) env('TRIGGERS_NODES_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Circuit breaker
    |--------------------------------------------------------------------------
    |
    | A trigger switches itself off, and its owners are notified, after this
    | many failed events in a row (Trigger::recordFailure()). One run that
    | starts resets the streak; switching it back on does too.
    |
    | A trigger can set its own limit (`config.max_failures`, the "Switch off
    | after failures in a row" setting) within `max_failures_range`.
    |
    | Separately, events dropped without running (out of credits, or the
    | last run still going under the "skip" overlap policy) switch it off
    | after `skips_before_disable` in a row: it fires far more often than
    | the workspace can actually run it (Trigger::recordSkip()).
    */

    'failures_before_disable' => (int) env('TRIGGERS_FAILURES_BEFORE_DISABLE', 3),

    'max_failures_range' => [1, 20],

    'skips_before_disable' => (int) env('TRIGGERS_SKIPS_BEFORE_DISABLE', 25),

    /*
    |--------------------------------------------------------------------------
    | Storm detector
    |--------------------------------------------------------------------------
    |
    | A trigger that takes in more than `max_events_per_minute` events in one
    | minute is switched off and its owners told (Trigger::recordStorm()): a
    | loop or bulk import upstream would otherwise start thousands of runs
    | and spend the workspace's credits before anyone looks. A trigger can set
    | its own limit with `config.storm_limit`. Triggers that group events or
    | wait for quiet are exempt, since collapsing a burst is what they are for.
    | "Run now" is never counted.
    */

    'storm' => [
        'enabled' => (bool) env('TRIGGERS_STORM_ENABLED', true),
        'max_events_per_minute' => (int) env('TRIGGERS_STORM_MAX_EVENTS_PER_MINUTE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Waiting for quiet (debounce)
    |--------------------------------------------------------------------------
    |
    | A trigger with `config.debounce_seconds` holds its events and runs once
    | when none has arrived for that long, with all of them. A source that
    | never goes quiet would hold forever, so the oldest held event also
    | forces a run after `max_wait_seconds`. Checked by triggers:flush-batches,
    | which runs every minute, so a wait is accurate to about a minute.
    */

    'debounce' => [
        'max_wait_seconds' => (int) env('TRIGGERS_DEBOUNCE_MAX_WAIT_SECONDS', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | `POST /api/hooks/{token}`: the token is the whole of the auth, so these
    | keep a leaked or hammered URL cheap.
    |
    | - hook_rate_limit_per_minute: per token (the `trigger-hooks` limiter,
    |   shared with Wait callbacks); excess gets a 429.
    | - max_webhook_body_kb: larger bodies get a 413 before anything is
    |   parsed or stored.
    | - payload_snippet_chars: how much of the raw body an event keeps for
    |   display, alongside the parsed payload.
    | - allowed_headers: the only request headers ever stored on an event.
    */

    'hook_rate_limit_per_minute' => (int) env('TRIGGERS_HOOK_RATE_LIMIT_PER_MINUTE', 120),

    'max_webhook_body_kb' => (int) env('TRIGGERS_MAX_WEBHOOK_BODY_KB', 1024),

    'payload_snippet_chars' => (int) env('TRIGGERS_PAYLOAD_SNIPPET_CHARS', 5000),

    /*
    | The longest a webhook set to "Respond with the result" holds the request
    | open waiting for its run to finish. The wait ties up a web worker, so
    | keep it well under the proxy's request timeout.
    */

    'max_sync_wait_seconds' => (int) env('TRIGGERS_MAX_SYNC_WAIT_SECONDS', 25),

    'allowed_headers' => [
        'X-Notion-Signature',
        'X-GitHub-Delivery',
        'X-GitHub-Event',
        'X-Hub-Signature-256',
        'Stripe-Signature',
        'X-Slack-Signature',
        'X-Slack-Request-Timestamp',
        'Content-Type',
        'User-Agent',
    ],

    /*
    |--------------------------------------------------------------------------
    | Schedules
    |--------------------------------------------------------------------------
    |
    | `triggers:run-due` (every minute) fires each schedule whose
    | `next_run_at` has arrived. A slot missed while the scheduler was down
    | still fires once if it's no older than `schedule_catch_up_minutes`;
    | older slots are skipped rather than fired late.
    */

    'schedule_catch_up_minutes' => (int) env('TRIGGERS_SCHEDULE_CATCH_UP_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Polling
    |--------------------------------------------------------------------------
    |
    | Plain polling triggers and app readers switched on as flow triggers.
    |
    | - poll_every_minutes: the interval for a plain polling trigger that
    |   doesn't set one. App readers bring their own default (1 minute for
    |   inboxes, chat and Sheets, 5 for the rest).
    | - min_poll_interval_minutes: no trigger polls more often than this.
    | - poll_max_items: items one poll looks at. The rest wait for the next
    |   poll, so one huge response can't start thousands of runs at once.
    | - poll_lock_seconds: how long a poll holds its trigger's lock if the
    |   worker dies mid-poll, before another poll may start.
    */

    'poll_every_minutes' => (int) env('TRIGGERS_POLL_EVERY_MINUTES', 15),

    'min_poll_interval_minutes' => (int) env('TRIGGERS_MIN_POLL_INTERVAL_MINUTES', 1),

    'poll_max_items' => (int) env('TRIGGERS_POLL_MAX_ITEMS', 100),

    /*
    | "Deleted" triggers (a row removed, an item deleted) have no feed to read:
    | they keep the ids that were there and report the ones that went missing
    | (PresenceTracker). `max_tracked` is how many ids one trigger may keep; a
    | longer list is refused with a message to narrow it, rather than watched
    | halfway.
    */

    'removal' => [
        'max_tracked' => (int) env('TRIGGERS_REMOVAL_MAX_TRACKED', 2000),
    ],

    /*
    | A trigger whose push subscription is working only polls this often, to
    | catch anything push missed. Without push it polls at its own interval.
    */

    'push_reconcile_poll_minutes' => (int) env('TRIGGERS_PUSH_RECONCILE_POLL_MINUTES', 15),

    'poll_lock_seconds' => (int) env('TRIGGERS_POLL_LOCK_SECONDS', 600),

    /*
    | Polling that backs off. A trigger without working push that finds nothing
    | new waits one more interval for every `after_empty_polls` empty polls in
    | a row, up to `max_multiplier` intervals; anything new starts it over.
    | Most polls of a quiet trigger find nothing, so this is where polling
    | cost goes. Off, every trigger polls at its own interval.
    */

    'adaptive_poll' => [
        'enabled' => (bool) env('TRIGGERS_ADAPTIVE_POLL', true),
        'after_empty_polls' => (int) env('TRIGGERS_ADAPTIVE_POLL_AFTER', 6),
        'max_multiplier' => (int) env('TRIGGERS_ADAPTIVE_POLL_MAX', 4),
    ],

    /*
    | A poll that fails with a temporary error (a rate limit, a 5xx, a dropped
    | connection) only counts toward the circuit breaker after this many in a
    | row. Any other error counts at once.
    */

    'poll_transient_failures_before_count' => (int) env('TRIGGERS_POLL_TRANSIENT_FAILURES_BEFORE_COUNT', 30),

    /*
    |--------------------------------------------------------------------------
    | Queues & the FireTriggerEvent job
    |--------------------------------------------------------------------------
    |
    | Polls and firings run on separate queues, so a burst of polling can't
    | delay a webhook-fired event sitting behind it. Each needs a Horizon
    | supervisor (config/horizon.php).
    |
    | Agent-targeted events fire on the shared `ai-agent` queue instead: the
    | job blocks on a model call, so it needs that supervisor's concurrency
    | ceiling (provider rate limits) and a timeout in minutes, not seconds.
    | `queue.retry_after` must stay above `agent_fire_timeout_seconds`, or a
    | still-running turn gets dispatched a second time.
    |
    | - fire_max_exceptions / fire_backoff_seconds: retries before an event
    |   is marked failed (one failure toward the circuit breaker).
    | - fire_lock: one firing per event at a time. `release_after` is how
    |   soon a blocked copy retries; `expire_after` frees a lock whose worker
    |   died.
    */

    'fire_queue' => env('TRIGGERS_FIRE_QUEUE', 'triggers-event'),

    'poll_queue' => env('TRIGGERS_POLL_QUEUE', 'triggers-poll'),

    'agent_fire_queue' => env('TRIGGERS_AGENT_FIRE_QUEUE', 'ai-agent'),

    'fire_timeout_seconds' => (int) env('TRIGGERS_FIRE_TIMEOUT_SECONDS', 120),

    'agent_fire_timeout_seconds' => (int) env('TRIGGERS_AGENT_FIRE_TIMEOUT_SECONDS', 320),

    'fire_max_exceptions' => (int) env('TRIGGERS_FIRE_MAX_EXCEPTIONS', 5),

    /*
    | Runs one workspace may have executing at once through its triggers (0 is
    | no limit). Events over the cap wait in the queue, `concurrency_release_
    | seconds` at a time, and are not dropped. A trigger can also set its own
    | cap with `config.max_concurrent_runs`.
    */

    'max_concurrent_runs_per_workspace' => (int) env('TRIGGERS_MAX_CONCURRENT_RUNS_PER_WORKSPACE', 25),

    'concurrency_release_seconds' => (int) env('TRIGGERS_CONCURRENCY_RELEASE_SECONDS', 15),

    'fire_backoff_seconds' => [10, 30, 90, 300, 900],

    'fire_lock' => [
        'release_after_seconds' => (int) env('TRIGGERS_FIRE_LOCK_RELEASE_AFTER_SECONDS', 30),
        'expire_after_seconds' => (int) env('TRIGGERS_FIRE_LOCK_EXPIRE_AFTER_SECONDS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stuck-event recovery (triggers:retry-stuck, every 5 minutes)
    |--------------------------------------------------------------------------
    |
    | Re-dispatches events left queued or running longer than these grace
    | periods (a lost job, a dead worker). The periods must comfortably
    | exceed normal processing time, or the command races live work.
    */

    'stuck' => [
        'queued_after_minutes' => (int) env('TRIGGERS_STUCK_QUEUED_AFTER_MINUTES', 10),
        'running_after_minutes' => (int) env('TRIGGERS_STUCK_RUNNING_AFTER_MINUTES', 15),
        'batch_size' => (int) env('TRIGGERS_STUCK_BATCH_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event history (triggers:prune-events, daily)
    |--------------------------------------------------------------------------
    |
    | Events older than `events_retention_days` are deleted, `prune_chunk`
    | rows at a time, at `prune_at` (server time). A polling trigger's old
    | events are compacted instead (payload dropped, delivery id kept): the
    | delivery id is what stops an item the endpoint still returns from
    | firing again.
    */

    'provider_events_retention_days' => (int) env('TRIGGERS_PROVIDER_EVENTS_RETENTION_DAYS', 7),

    'events_retention_days' => (int) env('TRIGGERS_EVENTS_RETENTION_DAYS', 30),

    'prune_chunk' => (int) env('TRIGGERS_PRUNE_CHUNK', 1000),

    'prune_at' => env('TRIGGERS_PRUNE_AT', '03:30'),

    /*
    |--------------------------------------------------------------------------
    | Instant (push) triggers
    |--------------------------------------------------------------------------
    |
    | Per provider switch. Off, a trigger just polls as before.
    |
    | Every watched provider (Gmail, Outlook, Drive, Calendar, GitHub) needs the
    | callback URL /api/hooks/apps/{provider} to be reachable from the internet
    | over HTTPS (APP_URL must be that address).
    |
    | Slack: the signing secret is that of the Slack app people connect through
    | Apps (the one behind the `slack` connector), not the assistant's app. In
    | that app, set the Events API request URL to /api/hooks/apps/slack and
    | subscribe to the bot events message.channels and message.groups, plus
    | message.im (direct messages), reaction_added, reaction_removed, pin_added,
    | and the others the Slack triggers use (app_mention, member_joined_channel,
    | channel_created, team_join, file_shared).
    */

    'push' => [
        // Telegram, Discord and Twilio are connected with the member's own bot or
        // account, so there is no platform app to set up: they are on unless switched off.
        // Each registers its webhook through the connected account (a public https APP_URL is needed).
        'telegram' => ['enabled' => (bool) env('TRIGGERS_PUSH_TELEGRAM_ENABLED', true)],
        'discord' => ['enabled' => (bool) env('TRIGGERS_PUSH_DISCORD_ENABLED', true), 'max_age_seconds' => 300],
        'twilio' => ['enabled' => (bool) env('TRIGGERS_PUSH_TWILIO_ENABLED', true)],
        // The marketing apps are connected with the member's own key too: each registers its webhook through it.
        'typeform' => ['enabled' => (bool) env('TRIGGERS_PUSH_TYPEFORM_ENABLED', true)],
        'mailchimp' => ['enabled' => (bool) env('TRIGGERS_PUSH_MAILCHIMP_ENABLED', true)],
        'kit' => ['enabled' => (bool) env('TRIGGERS_PUSH_KIT_ENABLED', true)],
        'activecampaign' => ['enabled' => (bool) env('TRIGGERS_PUSH_ACTIVECAMPAIGN_ENABLED', true)],
        'resend' => ['enabled' => (bool) env('TRIGGERS_PUSH_RESEND_ENABLED', true)],
        'postmark' => ['enabled' => (bool) env('TRIGGERS_PUSH_POSTMARK_ENABLED', true)],
        'mailgun' => ['enabled' => (bool) env('TRIGGERS_PUSH_MAILGUN_ENABLED', true)],
        // Payments: registers a webhook endpoint through the connected account.
        'stripe' => ['enabled' => (bool) env('TRIGGERS_PUSH_STRIPE_ENABLED', true)],
        // Shopify: a webhook per topic, registered through the store's own access token.
        'shopify' => ['enabled' => (bool) env('TRIGGERS_PUSH_SHOPIFY_ENABLED', true)],
        // Project tools: each registers its webhook through the connected account.
        'trello' => ['enabled' => (bool) env('TRIGGERS_PUSH_TRELLO_ENABLED', true)],
        'asana' => ['enabled' => (bool) env('TRIGGERS_PUSH_ASANA_ENABLED', true)],
        'clickup' => ['enabled' => (bool) env('TRIGGERS_PUSH_CLICKUP_ENABLED', true)],
        'monday' => ['enabled' => (bool) env('TRIGGERS_PUSH_MONDAY_ENABLED', true)],
        'airtable' => ['enabled' => (bool) env('TRIGGERS_PUSH_AIRTABLE_ENABLED', true)],
        'pipedrive' => ['enabled' => (bool) env('TRIGGERS_PUSH_PIPEDRIVE_ENABLED', true)],
        'zoho' => ['enabled' => (bool) env('TRIGGERS_PUSH_ZOHO_ENABLED', true)],
        'zoho_desk' => ['enabled' => (bool) env('TRIGGERS_PUSH_ZOHO_DESK_ENABLED', true)],

        'slack' => [
            'enabled' => (bool) env('TRIGGERS_PUSH_SLACK_ENABLED', false),
            'signing_secret' => env('TRIGGERS_SLACK_SIGNING_SECRET', env('SLACK_SIGNING_SECRET')),
            'max_age_seconds' => 300,
        ],

        // Gmail: a Pub/Sub topic Gmail may publish to, and a push subscription
        // on it calling /api/hooks/apps/gmail?token={verification_token}.
        'gmail' => [
            'enabled' => (bool) env('TRIGGERS_PUSH_GMAIL_ENABLED', false),
            'topic' => env('TRIGGERS_GMAIL_PUBSUB_TOPIC'),
            'verification_token' => env('TRIGGERS_GMAIL_PUSH_TOKEN'),
        ],

        // Outlook (Microsoft Graph), Drive, Calendar and GitHub register their
        // own watches through the connected account, so a switch is all they need.
        'outlook' => ['enabled' => (bool) env('TRIGGERS_PUSH_OUTLOOK_ENABLED', false)],
        'google_drive' => ['enabled' => (bool) env('TRIGGERS_PUSH_GOOGLE_DRIVE_ENABLED', false)],
        'google_calendar' => ['enabled' => (bool) env('TRIGGERS_PUSH_GOOGLE_CALENDAR_ENABLED', false)],
        'github' => ['enabled' => (bool) env('TRIGGERS_PUSH_GITHUB_ENABLED', false)],

        // Sheets and Docs watch the file through Drive's push channels, so like
        // Drive and Calendar they need only the switch (and the connected
        // account's Drive access).
        'google_sheets' => ['enabled' => (bool) env('TRIGGERS_PUSH_GOOGLE_SHEETS_ENABLED', false)],
        'google_docs' => ['enabled' => (bool) env('TRIGGERS_PUSH_GOOGLE_DOCS_ENABLED', false)],

        // Forms tells a Pub/Sub topic about new responses: the topic must let
        // forms-notifications@system.gserviceaccount.com publish, and a push
        // subscription on it must call /api/hooks/apps/google_forms?token={verification_token}.
        'google_forms' => [
            'enabled' => (bool) env('TRIGGERS_PUSH_GOOGLE_FORMS_ENABLED', false),
            'topic' => env('TRIGGERS_GOOGLE_FORMS_PUBSUB_TOPIC'),
            'verification_token' => env('TRIGGERS_GOOGLE_FORMS_PUSH_TOKEN'),
        ],

        // YouTube announces new uploads of a channel through Google's WebSub hub
        // (no Google Cloud setup): the hub calls /api/hooks/apps/google_youtube.
        'google_youtube' => [
            'enabled' => (bool) env('TRIGGERS_PUSH_GOOGLE_YOUTUBE_ENABLED', false),
            'hub_url' => env('TRIGGERS_YOUTUBE_WEBSUB_HUB', 'https://pubsubhubbub.appspot.com/subscribe'),
            'lease_seconds' => 432000,
        ],

        // Notion sends one webhook URL per integration: in the integration's
        // Webhooks tab, set it to /api/hooks/apps/notion. Notion first posts a
        // verification_token (logged here once); paste it back in Notion and
        // set it as the verification token.
        'notion' => [
            'enabled' => (bool) env('TRIGGERS_PUSH_NOTION_ENABLED', false),
            'verification_token' => env('TRIGGERS_NOTION_VERIFICATION_TOKEN'),
        ],

        // Linear and Jira register a webhook per trigger through the connected
        // account, so a switch is all they need. Linear signs each webhook with its
        // own secret; `max_age_seconds` is how old a delivery may be. Registering a
        // webhook needs an account connected with Linear's `admin` scope and Jira's
        // `manage:jira-webhook` scope, which the connectors ask for only while their
        // switch is on (an account connected earlier must reconnect).
        'linear' => [
            'enabled' => (bool) env('TRIGGERS_PUSH_LINEAR_ENABLED', false),
            'max_age_seconds' => 300,
        ],
        'jira' => ['enabled' => (bool) env('TRIGGERS_PUSH_JIRA_ENABLED', false)],
    ],

];
