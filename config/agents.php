<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Agent Categories
    |--------------------------------------------------------------------------
    |
    | The curated catalog of categories an agent may be classified under. The
    | metadata endpoint merges these with any distinct categories already in
    | use across a workspace's agents.
    |
    */

    'categories' => [
        ['value' => 'general', 'label' => 'General'],
        ['value' => 'sales', 'label' => 'Sales'],
        ['value' => 'marketing', 'label' => 'Marketing'],
        ['value' => 'support', 'label' => 'Customer Support'],
        ['value' => 'research', 'label' => 'Research'],
        ['value' => 'engineering', 'label' => 'Engineering'],
        ['value' => 'data', 'label' => 'Data & Analytics'],
        ['value' => 'operations', 'label' => 'Operations'],
        ['value' => 'finance', 'label' => 'Finance'],
        ['value' => 'hr', 'label' => 'Human Resources'],
        ['value' => 'productivity', 'label' => 'Productivity'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Internal (platform-owned) Agents
    |--------------------------------------------------------------------------
    |
    | Provider/model resolution for the code-defined agents registered in
    | App\Agents\Internal\Registry. Resolution order per call:
    | per-agent override here → the calling agent's provider/model → defaults.
    | Leave `defaults` values null to always inherit from the caller.
    |
    */

    'internal' => [
        'defaults' => [
            'provider' => env('INTERNAL_AGENT_PROVIDER'),
            'model' => env('INTERNAL_AGENT_MODEL'),
        ],
        'overrides' => [
            // 'planner' => ['provider' => 'anthropic', 'model' => 'claude-sonnet-4-6'],
            // 'moderation' => ['provider' => 'anthropic', 'model' => 'claude-3-5-haiku-latest'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Trigger Types
    |--------------------------------------------------------------------------
    |
    | The trigger types an agent supports, each described with the shape of the
    | `config` object the client should send when creating a trigger of that
    | type. Kept in sync with StoreAgentTriggerRequest's allowed values.
    |
    */

    'trigger_types' => [
        [
            'value' => 'schedule',
            'label' => 'Schedule',
            'description' => 'Run the agent automatically on a recurring cron schedule.',
            'config_schema' => [
                'cron' => ['type' => 'string', 'required' => true, 'example' => '0 9 * * 1', 'description' => 'Standard 5-field cron expression.'],
                'timezone' => ['type' => 'string', 'required' => false, 'example' => 'UTC', 'description' => 'IANA timezone the cron is evaluated in.'],
            ],
        ],
        [
            'value' => 'webhook',
            'label' => 'Webhook',
            'description' => 'Run the agent when an external service POSTs to the trigger URL.',
            'config_schema' => [
                'secret' => ['type' => 'string', 'required' => false, 'description' => 'Optional shared secret validated against the X-Webhook-Secret header.'],
            ],
        ],
        [
            'value' => 'event',
            'label' => 'Event',
            'description' => 'Run the agent when a matching internal platform event is emitted.',
            'config_schema' => [
                'event' => ['type' => 'string', 'required' => true, 'description' => 'The internal event name to listen for.'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Catalog
    |--------------------------------------------------------------------------
    |
    | A curated catalog of text-generation models per provider surfaced to the
    | agent builder. Only providers that are actually configured (their API key
    | is present in config/ai.php) are returned by the metadata endpoint.
    |
    */

    'models' => [
        'anthropic' => [
            ['id' => 'claude-sonnet-4-5', 'label' => 'Claude Sonnet 4.5', 'context_window' => 200000],
            ['id' => 'claude-opus-4-1', 'label' => 'Claude Opus 4.1', 'context_window' => 200000],
            ['id' => 'claude-3-5-haiku-latest', 'label' => 'Claude 3.5 Haiku', 'context_window' => 200000],
        ],
        'openai' => [
            ['id' => 'gpt-4o', 'label' => 'GPT-4o', 'context_window' => 128000],
            ['id' => 'gpt-4o-mini', 'label' => 'GPT-4o mini', 'context_window' => 128000],
            ['id' => 'gpt-4.1', 'label' => 'GPT-4.1', 'context_window' => 1000000],
            ['id' => 'o3-mini', 'label' => 'o3-mini', 'context_window' => 200000],
        ],
        'gemini' => [
            ['id' => 'gemini-2.5-pro', 'label' => 'Gemini 2.5 Pro', 'context_window' => 1000000],
            ['id' => 'gemini-2.0-flash', 'label' => 'Gemini 2.0 Flash', 'context_window' => 1000000],
            ['id' => 'gemini-2.0-flash-lite', 'label' => 'Gemini 2.0 Flash Lite', 'context_window' => 1000000],
        ],
        'groq' => [
            ['id' => 'llama-3.3-70b-versatile', 'label' => 'Llama 3.3 70B Versatile', 'context_window' => 128000],
            ['id' => 'llama-3.1-8b-instant', 'label' => 'Llama 3.1 8B Instant', 'context_window' => 128000],
        ],
        'xai' => [
            ['id' => 'grok-4', 'label' => 'Grok 4', 'context_window' => 256000],
            ['id' => 'grok-3-mini', 'label' => 'Grok 3 Mini', 'context_window' => 131072],
        ],
        'mistral' => [
            ['id' => 'mistral-large-latest', 'label' => 'Mistral Large', 'context_window' => 128000],
            ['id' => 'mistral-small-latest', 'label' => 'Mistral Small', 'context_window' => 128000],
        ],
        'deepseek' => [
            ['id' => 'deepseek-chat', 'label' => 'DeepSeek Chat', 'context_window' => 64000],
            ['id' => 'deepseek-reasoner', 'label' => 'DeepSeek Reasoner', 'context_window' => 64000],
        ],
        'openrouter' => [
            ['id' => 'openai/gpt-4o', 'label' => 'GPT-4o (OpenRouter)', 'context_window' => 128000],
            ['id' => 'anthropic/claude-sonnet-4.5', 'label' => 'Claude Sonnet 4.5 (OpenRouter)', 'context_window' => 200000],
        ],
        'anyapi' => [
            ['id' => 'openai/gpt-4o-mini', 'label' => 'GPT-4o mini (AnyAPI)', 'context_window' => 128000],
            ['id' => 'anthropic/claude-sonnet-4.6', 'label' => 'Claude Sonnet 4.6 (AnyAPI)', 'context_window' => 200000],
        ],
    ],

];
