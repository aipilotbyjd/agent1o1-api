<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an operation needs a Stripe customer the workspace doesn't have
 * yet: mapped to 409 in `bootstrap/app.php`.
 *
 * A workspace only becomes a Stripe customer by going through checkout, so
 * this means "you have no billing relationship to manage", not "something went
 * wrong". The frontend should send the customer to a plan/checkout screen
 * rather than surfacing an error.
 */
class BillingAccountNotFoundException extends RuntimeException
{
    public function __construct(string $message = 'This workspace does not have a billing account yet. Subscribe to a plan first.')
    {
        parent::__construct($message);
    }
}
