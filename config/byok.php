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
| `free_tier` (optional) is a one-line note the screens show for a provider
| that can be used without paying, so a new workspace can start at no cost.
|
| `connect` (optional) is one-click connect: the member signs in at the
| provider and comes back with a working credential, nothing to copy. See
| `App\Services\Ai\Connect\AiProviderConnectFlow`. `driver` is how:
|  - `openrouter`: OpenRouter's PKCE flow issues a real API key.
|  - `oauth`: an OAuth 2 authorization-code flow with PKCE returns an
|    access token (refreshed before it expires) that is sent as the key.
| A provider's connect is offered only when `enabled` is true and, for
| `oauth`, a `client_id` is set.
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
            // Sign in with ChatGPT: calls run on the member's ChatGPT plan.
            // Off until OpenAI approves this app (https://openai.com/form/sign-in-with-chatgpt-interest/);
            // the URLs and scopes are env-overridable to match what the approval names.
            'connect' => [
                'driver' => 'oauth',
                'label' => 'Sign in with ChatGPT',
                'enabled' => (bool) env('BYOK_CONNECT_CHATGPT_ENABLED', false),
                'client_id' => env('CHATGPT_OAUTH_CLIENT_ID'),
                'client_secret' => env('CHATGPT_OAUTH_CLIENT_SECRET'),
                'authorize_url' => env('CHATGPT_OAUTH_AUTHORIZE_URL', 'https://auth.openai.com/api/accounts/authorize'),
                'token_url' => env('CHATGPT_OAUTH_TOKEN_URL', 'https://auth.openai.com/api/accounts/oauth/token'),
                'scopes' => explode(' ', (string) env('CHATGPT_OAUTH_SCOPES', 'openid profile email offline_access')),
                // ChatGPT plan calls must not be stored by OpenAI.
                'provider_overrides' => ['store' => false],
            ],
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
            'free_tier' => 'Free tier in Google AI Studio. No card needed.',
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
            'free_tier' => 'Free tier with rate limits. No card needed.',
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
            'free_tier' => 'Free (“:free”) models work with no credit.',
            'connect' => [
                'driver' => 'openrouter',
                'label' => 'Connect with OpenRouter',
                'enabled' => (bool) env('BYOK_CONNECT_OPENROUTER_ENABLED', true),
                'authorize_url' => 'https://openrouter.ai/auth',
                'exchange_url' => 'https://openrouter.ai/api/v1/auth/keys',
                'key_label' => env('BYOK_CONNECT_KEY_LABEL', env('APP_NAME', 'Agent 101')),
            ],
        ],
        'huggingface' => [
            'label' => 'Hugging Face',
            'key_url' => 'https://huggingface.co/settings/tokens',
            'key_placeholder' => 'hf_...',
            'key_prefix' => 'hf_',
            'key_guide' => [
                'Sign in to huggingface.co and open Settings → Access Tokens.',
                'Create a fine-grained token with “Make calls to Inference Providers” and copy it.',
                'Inference Providers usage is billed to your Hugging Face account.',
            ],
            'check' => ['url' => 'https://huggingface.co/api/whoami-v2', 'auth' => 'bearer'],
            'connect' => [
                'driver' => 'oauth',
                'label' => 'Connect with Hugging Face',
                'enabled' => (bool) env('BYOK_CONNECT_HUGGINGFACE_ENABLED', true),
                'client_id' => env('HUGGINGFACE_OAUTH_CLIENT_ID'),
                'client_secret' => env('HUGGINGFACE_OAUTH_CLIENT_SECRET'),
                'authorize_url' => 'https://huggingface.co/oauth/authorize',
                'token_url' => 'https://huggingface.co/oauth/token',
                'scopes' => ['openid', 'profile', 'inference-api'],
            ],
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

    /*
    | One-click connect. `redirect_path` is the frontend page the provider
    | sends the member back to (`APP_FRONTEND_URL` + path), which finishes
    | the connection; register that full URL with each `oauth` provider.
    | `refresh_before_seconds` is how long before an OAuth token expires it
    | is renewed (`ai-credentials:refresh-tokens`, every five minutes).
    */
    'connect' => [
        'redirect_path' => env('BYOK_CONNECT_REDIRECT_PATH', '/oauth/ai-provider-complete'),
        'state_ttl_minutes' => 10,
        'refresh_before_seconds' => 900,
    ],

];
