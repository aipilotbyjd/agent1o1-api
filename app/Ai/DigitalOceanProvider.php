<?php

namespace App\Ai;

use Laravel\Ai\Contracts\Gateway\TextGateway;
use Laravel\Ai\Providers\OpenRouterProvider;

/**
 * DigitalOcean Gradient AI Platform serverless inference provider.
 *
 * Behaves like OpenRouter (OpenAI-compatible /chat/completions endpoint,
 * unprefixed model IDs from DO's model garden) but routes text generation
 * through {@see DigitalOceanTextGateway} so structured output is sent with
 * strict=false.
 */
class DigitalOceanProvider extends OpenRouterProvider
{
    public function textGateway(): TextGateway
    {
        return $this->textGateway ??= new DigitalOceanTextGateway($this->events);
    }
}
