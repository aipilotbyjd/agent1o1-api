<?php

namespace App\Enums\Ai;

/**
 * How a workspace's AI provider credential was obtained, and so how it is
 * kept working: see `AiProviderCredential`.
 */
enum AiProviderAuthMethod: string
{
    /** A key pasted in, or one a provider issued through one-click connect (OpenRouter). It does not expire on its own. */
    case ApiKey = 'api_key';

    /** An OAuth access token from one-click connect (Hugging Face, ChatGPT). It expires and is refreshed with its refresh token. */
    case OAuth = 'oauth';
}
