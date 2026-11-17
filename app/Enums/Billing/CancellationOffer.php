<?php

namespace App\Enums\Billing;

/**
 * The single alternative the cancellation flow may show before a customer
 * confirms. `Downgrade` is only ever a link to the plans screen: nothing is
 * accepted through the cancellation endpoints for it.
 */
enum CancellationOffer: string
{
    case Discount = 'discount';
    case Downgrade = 'downgrade';
    case Help = 'help';

    /**
     * Whether taking this offer is an action of its own, rather than the
     * customer going elsewhere in the app.
     */
    public function isAcceptable(): bool
    {
        return $this !== self::Downgrade;
    }
}
