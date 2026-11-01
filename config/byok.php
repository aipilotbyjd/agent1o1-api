<?php

/*
|--------------------------------------------------------------------------
| Bring Your Own Key
|--------------------------------------------------------------------------
|
| The AI providers a workspace may connect its own API key for, keyed by the
| `config/ai.php` provider name that `model_routes.execution_provider`
| uses. A key stored for one of these runs every catalog model routed
| through that provider (see `App\Services\Ai\ByokProviderRegistrar`).
|
| `key_prefix` is what a key for the provider starts with (empty when it
| has no fixed shape): the settings screen uses it to spot a key pasted
| under the wrong provider. `key_guide` is the short "where do I find my
| key" walk-through it shows.
|
| `check` is how a key is verified before it is trusted: an authenticated
| GET that succeeds only for a working key. `auth` is how the key is sent:
| `bearer` (Authorization header), `anthropic` (x-api-key header) or
| `query` (?key=). Platform-internal gateways are deliberately absent.
|
*/

return [

    'providers' => [
        'openai' => [
            'label' => 'OpenAI',
            'key_url' => 'https://platform.openai.com/api-keys',
            'key_placeholder' => 'sk-...',
            'key_prefix' => 'sk-',
            'key_guide' => [
                'Sign in to platform.openai.com and open API keys.',
                'Click “Create new secret key”, name it, and copy it.',
                'Make sure billing is set up: a key with no credit is rejected.',
            ],
            'check' => ['url' => 'https://api.openai.com/v1/models', 'auth' => 'bearer'],
        ],
        'anthropic' => [
            'label' => 'Anthropic',
            'key_url' => 'https://console.anthropic.com/settings/keys',
            'key_placeholder' => 'sk-ant-...',
            'key_prefix' => 'sk-ant-',
            'key_guide' => [
                'Sign in to console.anthropic.com and open API keys.',
                'Click “Create key”, name it, and copy it.',
                'Add credits under Billing so the key can make calls.',
            ],
            'check' => ['url' => 'https://api.anthropic.com/v1/models', 'auth' => 'anthropic'],
        ],
        'gemini' => [
            'label' => 'Google Gemini',
            'key_url' => 'https://aistudio.google.com/app/apikey',
            'key_placeholder' => 'AIza...',
            'key_prefix' => 'AIza',
            'key_guide' => [
                'Open Google AI Studio and go to “Get API key”.',
                'Create a key in a Google Cloud project and copy it.',
            ],
            'check' => ['url' => 'https://generativelanguage.googleapis.com/v1beta/models', 'auth' => 'query'],
        ],
        'mistral' => [
            'label' => 'Mistral',
            'key_url' => 'https://console.mistral.ai/api-keys',
            'key_placeholder' => '',
            'key_prefix' => '',
            'key_guide' => [
                'Sign in to console.mistral.ai and open API keys.',
                'Click “Create new key” and copy it.',
            ],
            'check' => ['url' => 'https://api.mistral.ai/v1/models', 'auth' => 'bearer'],
        ],
        'deepseek' => [
            'label' => 'DeepSeek',
            'key_url' => 'https://platform.deepseek.com/api_keys',
            'key_placeholder' => 'sk-...',
            'key_prefix' => 'sk-',
            'key_guide' => [
                'Sign in to platform.deepseek.com and open API keys.',
                'Create a key, copy it, and top up your balance.',
            ],
            'check' => ['url' => 'https://api.deepseek.com/models', 'auth' => 'bearer'],
        ],
        'xai' => [
            'label' => 'xAI',
            'key_url' => 'https://console.x.ai',
            'key_placeholder' => 'xai-...',
            'key_prefix' => 'xai-',
            'key_guide' => [
                'Sign in to console.x.ai and open API keys.',
                'Create a key and copy it.',
            ],
            'check' => ['url' => 'https://api.x.ai/v1/models', 'auth' => 'bearer'],
        ],
        'groq' => [
            'label' => 'Groq',
            'key_url' => 'https://console.groq.com/keys',
            'key_placeholder' => 'gsk_...',
            'key_prefix' => 'gsk_',
            'key_guide' => [
                'Sign in to console.groq.com and open API keys.',
                'Click “Create API key” and copy it.',
            ],
            'check' => ['url' => 'https://api.groq.com/openai/v1/models', 'auth' => 'bearer'],
        ],
        'openrouter' => [
            'label' => 'OpenRouter',
            'key_url' => 'https://openrouter.ai/settings/keys',
            'key_placeholder' => 'sk-or-...',
            'key_prefix' => 'sk-or-',
            'key_guide' => [
                'Sign in to openrouter.ai and open Settings → Keys.',
                'Click “Create key” and copy it.',
                'Free (“:free”) models work without credit; others need credit.',
            ],
            // `/models` is public; `/key` is the endpoint that needs a real key.
            'check' => ['url' => 'https://openrouter.ai/api/v1/key', 'auth' => 'bearer'],
        ],
        'fireworks' => [
            'label' => 'Fireworks AI',
            'key_url' => 'https://fireworks.ai/account/api-keys',
            'key_placeholder' => 'fw_...',
            'key_prefix' => 'fw_',
            'key_guide' => [
                'Sign in to fireworks.ai and open Account → API keys.',
                'Create a key and copy it.',
            ],
            'check' => ['url' => 'https://api.fireworks.ai/inference/v1/models', 'auth' => 'bearer'],
        ],
        'together' => [
            'label' => 'Together AI',
            'key_url' => 'https://api.together.ai/settings/api-keys',
            'key_placeholder' => '',
            'key_prefix' => '',
            'key_guide' => [
                'Sign in to api.together.ai and open Settings → API keys.',
                'Copy your key or create a new one.',
            ],
            'check' => ['url' => 'https://api.together.xyz/v1/models', 'auth' => 'bearer'],
        ],
    ],

    /*
    | Background re-checks: a key not checked within this many hours is
    | checked again by `ai-credentials:check` (scheduled hourly).
    */
    'recheck_after_hours' => (int) env('BYOK_RECHECK_AFTER_HOURS', 6),

];
