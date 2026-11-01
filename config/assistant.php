<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Brand
    |--------------------------------------------------------------------------
    |
    | The personal assistant's public identity. This is the only place the
    | brand name may appear: code, tables, routes and prompts stay neutral
    | ("assistant") and read it through `BrandRepository`, so a rename is an
    | `.env` change. See docs/ASSISTANT_BRANDING_PLAN.md.
    |
    | Feature names may contain `:name`, replaced with the brand name.
    |
    */

    'brand' => [
        'name' => env('ASSISTANT_NAME', 'Orb'),
        'tagline' => env('ASSISTANT_TAGLINE', 'Your personal AI agent'),
        'description' => env('ASSISTANT_DESCRIPTION', 'Reads your apps, preps your day and handles your inbox.'),
        'emoji' => env('ASSISTANT_EMOJI', '🔮'),
        'color' => env('ASSISTANT_COLOR', '#7C3AED'),
        'icon_url' => env('ASSISTANT_ICON_URL', '/brand/assistant/icon.svg'),
        'avatar_url' => env('ASSISTANT_AVATAR_URL', '/brand/assistant/icon.svg'),

        'features' => [
            'daily' => env('ASSISTANT_FEATURE_DAILY', ':name Daily'),
            'inbox' => env('ASSISTANT_FEATURE_INBOX', ':name Inbox'),
            'meeting_prep' => env('ASSISTANT_FEATURE_PREP', ':name Prep'),
            'situations' => env('ASSISTANT_FEATURE_SITUATIONS', 'Situations'),
        ],

        'email_local_part' => env('ASSISTANT_EMAIL', 'orb'),
        'email_aliases' => array_values(array_filter(array_map('trim', explode(',', (string) env('ASSISTANT_EMAIL_ALIASES', ''))))),
        'inbound_domain' => env('ASSISTANT_INBOUND_DOMAIN', 'agent1o1.com'),
        'sms_signature' => env('ASSISTANT_SMS_SIGNATURE', '- :name'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Runtime limits
    |--------------------------------------------------------------------------
    */

    'limits' => [
        'instructions_max_chars' => 4000,
        'session_title_max_chars' => 120,
        'message_max_chars' => 20000,
        'style_max_chars' => 2000,
        'feedback_comment_max_chars' => 1000,

        // Every saved fact is sent on every turn, so they are kept few and short.
        'memory_key_max_chars' => 64,
        'memory_value_max_chars' => 500,
        'memories_max' => 100,
    ],

    'runtime' => [
        // The model when the owner hasn't picked one: this internal catalog
        // entry's routes (see ModelCatalogDataset), or (if it has none)
        // the raw provider/model below.
        'catalog' => env('ASSISTANT_MODEL_CATALOG', 'personal-assistant'),
        'provider' => env('ASSISTANT_PROVIDER', 'anthropic'),
        'model' => env('ASSISTANT_MODEL'),

        // Model calls per turn (each tool round trip is one step).
        'max_steps' => (int) env('ASSISTANT_MAX_STEPS', 100),

        // A tool that failed this many times in one turn is refused, so a
        // model that can't get its arguments right stops and explains.
        'max_tool_failures_per_turn' => 3,

        // Most recent messages replayed to the model.
        'history_limit' => 60,

        // Characters of an earlier tool result replayed in later turns.
        'replayed_result_chars' => 4000,

        // A running turn older than this no longer holds the session.
        'turn_stale_after_minutes' => 30,

        // See the `redis-assistant` connection in config/queue.php.
        'queue_connection' => env('ASSISTANT_QUEUE_CONNECTION', 'redis-assistant'),
        'queue' => 'ai-assistant',

        // Seconds a turn job may run; must stay below the connection's retry_after.
        'job_timeout' => 1200,
    ],

    'context' => [
        // Fold older messages into a recap once a conversation has more
        // than this many, or once it fills this share of the model's window.
        'compact_after_messages' => 40,
        'compact_at_ratio' => 0.8,

        // Most recent messages always kept word for word.
        'keep_recent_messages' => 12,

        // Used when the model's catalog entry doesn't state its window.
        'default_window_tokens' => 128000,

        // Rough token estimate for the context meter and the threshold.
        'chars_per_token' => 4,
    ],

    'transcription' => [
        'provider' => env('ASSISTANT_TRANSCRIPTION_PROVIDER', 'openai'),
        'model' => env('ASSISTANT_TRANSCRIPTION_MODEL'),
        'max_kilobytes' => 25600,
    ],

    'briefings' => [
        // How far back the very first report looks.
        'first_run_lookback_hours' => 16,

        // Items read from each app per report: keeps the prompt bounded.
        'max_items_per_source' => 25,

        'max_situations' => 5,

        // Writing a whole report takes longer than a chat reply.
        'writer_timeout_seconds' => 180,
        'max_connectors' => 50,
        'instructions_max_chars' => 4000,
    ],

    'meetings' => [
        // How far ahead the calendar is synced and listed.
        'sync_days' => 7,
        'default_minutes_before' => 30,

        // How far back Meeting Prep looks for email with the attendees.
        'email_lookback_days' => 90,
        'max_emails' => 10,
        'max_files' => 5,
    ],

    'inbox' => [
        'max_labels' => 25,
        'max_labels_per_message' => 5,

        // Labels the classifier is less sure of than this are dropped.
        'min_confidence' => 0.6,

        // New messages handled per check, per mailbox.
        'max_messages_per_check' => 20,

        // Past replies to the same sender used to match the owner's voice.
        'past_replies' => 3,

        'drafting_instructions_max_chars' => 2000,
    ],

    'follow_ups' => [
        // Email threads watched for a reply at once, and the longest wait.
        'max_pending' => 50,
        'max_days' => 60,
    ],

    'triggers' => [
        'max_per_assistant' => 50,

        // A trigger switches itself off after this many failed runs in a row.
        'max_consecutive_failures' => 3,

        'webhook_rate_per_minute' => 100,
        'webhook_payload_max_chars' => 10000,
        'prompt_max_chars' => 4000,
    ],

    'sandbox' => [
        // The cloud computer the assistant runs code on (E2B). Leave the key
        // empty to turn code running off.
        'provider' => 'e2b',
        'api_key' => env('E2B_API_KEY'),
        'api_url' => env('E2B_API_URL', 'https://api.e2b.dev'),
        'domain' => env('E2B_DOMAIN', 'e2b.app'),
        'template' => env('E2B_TEMPLATE', 'code-interpreter-v1'),

        // A conversation's computer is deleted after this long idle.
        'idle_seconds' => 900,

        // Longest one run may take, and how much output reaches the model.
        'run_timeout_seconds' => 600,
        'max_output_chars' => 20000,
        'max_file_kilobytes' => 20480,

        'credits_per_minute' => 2,
    ],

    'channels' => [
        'email' => [
            // The secret your inbound mail provider (e.g. Postmark) puts in
            // the webhook URL: /api/hooks/assistant/email?token=…
            'inbound_token' => env('ASSISTANT_EMAIL_INBOUND_TOKEN'),

            // Only act on mail whose sender passed SPF or DKIM.
            'require_authentication' => (bool) env('ASSISTANT_EMAIL_REQUIRE_AUTH', true),
        ],

        // The platform's own Slack app, installed per Slack workspace with
        // "Add to Slack". Leave empty to hide Slack.
        'slack' => [
            'client_id' => env('ASSISTANT_SLACK_CLIENT_ID'),
            'client_secret' => env('ASSISTANT_SLACK_CLIENT_SECRET'),
            'signing_secret' => env('ASSISTANT_SLACK_SIGNING_SECRET'),
            'scopes' => 'chat:write,im:history,im:read,im:write,users:read,users:read.email',
        ],

        // Texting the assistant (Twilio). Leave the SID empty to hide SMS.
        'sms' => [
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('ASSISTANT_SMS_FROM'),

            // The exact URL Twilio posts to, if a proxy changes what the app sees.
            'webhook_url' => env('ASSISTANT_SMS_WEBHOOK_URL'),

            'code_minutes' => 10,
            'resend_seconds' => 60,
            'attempts_per_code' => 5,
            'codes_per_hour' => 5,

            'chunk_chars' => 1500,
            'max_chunks' => 6,
            'per_minute' => 20,
            'per_hour' => 200,

            // A text within this many minutes continues the last conversation;
            // after this many days it starts a new one; in between the model decides.
            'continue_within_minutes' => 20,
            'new_after_days' => 7,
        ],
    ],

    'approvals' => [
        'ttl_minutes' => (int) env('ASSISTANT_APPROVAL_TTL_MINUTES', 1440),
    ],

];
