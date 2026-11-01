<?php

namespace App\Services\SuperAdmin\Billing;

use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;

/**
 * Fetches a Stripe event by id. Kept apart so tests can swap it for a fake
 * and never reach Stripe.
 */
class StripeEvents
{
    /**
     * @return array<string, mixed>
     *
     * @throws ApiErrorException
     */
    public function retrieve(string $eventId): array
    {
        return Cashier::stripe()->events->retrieve($eventId)->toArray();
    }
}
