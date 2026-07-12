<?php

namespace App\Ai;

use Laravel\Ai\Contracts\Gateway\TextGateway;
use Laravel\Ai\Providers\OpenRouterProvider;

/**
 * AnyAPI provider — behaves like OpenRouter (prefixed model names, the
 * /chat/completions endpoint) but routes text generation through
 * {@see AnyApiTextGateway} so structured output is sent with strict=false.
 */
class AnyApiProvider extends OpenRouterProvider
{
    public function textGateway(): TextGateway
    {
        return $this->textGateway ??= new AnyApiTextGateway($this->events);
    }
}
