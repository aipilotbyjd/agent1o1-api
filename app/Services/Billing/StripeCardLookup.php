<?php

namespace App\Services\Billing;

use Laravel\Cashier\Cashier;
use Throwable;

/**
 * Reads the card behind a Stripe subscription: its fingerprint (the same
 * for the same card number across customers) and its funding type
 * (`credit`, `debit`, `prepaid`). Returns nulls rather than failing when
 * Stripe cannot be reached, so a webhook never fails on it.
 */
class StripeCardLookup
{
    /**
     * @param  array<string, mixed>  $subscription  The Stripe subscription object.
     * @return array{fingerprint: string|null, funding: string|null}
     */
    public function forSubscription(array $subscription): array
    {
        $paymentMethod = $subscription['default_payment_method'] ?? null;

        if (is_array($paymentMethod)) {
            return $this->fromPaymentMethod($paymentMethod);
        }

        if (! is_string($paymentMethod) || $paymentMethod === '') {
            return ['fingerprint' => null, 'funding' => null];
        }

        try {
            return $this->fromPaymentMethod(Cashier::stripe()->paymentMethods->retrieve($paymentMethod)->toArray());
        } catch (Throwable $exception) {
            report($exception);

            return ['fingerprint' => null, 'funding' => null];
        }
    }

    /**
     * @param  array<string, mixed>  $paymentMethod
     * @return array{fingerprint: string|null, funding: string|null}
     */
    private function fromPaymentMethod(array $paymentMethod): array
    {
        return [
            'fingerprint' => $paymentMethod['card']['fingerprint'] ?? null,
            'funding' => $paymentMethod['card']['funding'] ?? null,
        ];
    }
}
