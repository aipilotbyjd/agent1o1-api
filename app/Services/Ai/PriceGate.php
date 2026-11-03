<?php

namespace App\Services\Ai;

use App\Enums\Ai\PriceEnforcement;
use Laravel\Pulse\Facades\Pulse;

/**
 * Whether a call may run on the platform's own key given how far its model's
 * price can be trusted (`billing.price_enforcement`). A call on a
 * workspace's own key never comes here: the workspace pays the provider.
 *
 * Under `warn`, every platform call on an untrusted price is counted to Pulse
 * (`ai_untrusted_price_call`) so the price book report can show exactly what
 * `enforce` would block before it is switched on.
 */
class PriceGate
{
    public function __construct(private readonly ModelPrices $prices) {}

    /**
     * A null model is the provider's default model (embeddings), which has
     * no catalog price to check, so it is always allowed.
     */
    public function allowsPlatformCall(string $provider, ?string $model): bool
    {
        $mode = PriceEnforcement::current();

        if ($mode === PriceEnforcement::Off || $model === null) {
            return true;
        }

        if ($this->prices->status($provider, $model)?->isTrusted()) {
            return true;
        }

        Pulse::record('ai_untrusted_price_call', "{$provider}:{$model}")->count();

        return $mode !== PriceEnforcement::Enforce;
    }

    public function enforcing(): bool
    {
        return PriceEnforcement::current() === PriceEnforcement::Enforce;
    }
}
