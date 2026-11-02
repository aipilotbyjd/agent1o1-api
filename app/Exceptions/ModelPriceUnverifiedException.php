<?php

namespace App\Exceptions;

/**
 * Every platform route a call could run on is for a model whose price has not
 * been verified, `billing.price_enforcement` is `enforce`, and the workspace
 * has no key of its own for it. Handled like a paused provider (a 503, and a
 * queued turn fails with this message): to the customer the model is simply
 * unavailable for now.
 */
class ModelPriceUnverifiedException extends ProviderPausedException
{
    public static function forProviders(): self
    {
        return new self('This model is not available yet. Pick another model, or add your own API key for it.');
    }
}
