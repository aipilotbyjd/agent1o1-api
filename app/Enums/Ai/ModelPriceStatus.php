<?php

namespace App\Enums\Ai;

/**
 * How far a model price can be trusted. Only a verified price may bill real
 * customers once `billing.price_enforcement` is `enforce`.
 */
enum ModelPriceStatus: string
{
    /** A placeholder for local development and demos: never real. */
    case Demo = 'demo';

    /** Entered or synced, but nobody has checked it against the provider yet. */
    case Unverified = 'unverified';

    /** Checked by an operator against the provider's price list or contract. */
    case Verified = 'verified';

    public function isTrusted(): bool
    {
        return $this === self::Verified;
    }
}
