<?php

namespace App\Services\Referrals;

use App\Enums\Billing\BillingInterval;
use App\Enums\Referrals\ReferralPaymentSource;

/**
 * One paid Stripe charge from a referred workspace, as the webhook saw it.
 * `reference` is the invoice id for subscription payments and the payment
 * intent id for one-off Checkout purchases; `amountCents` excludes tax.
 */
final readonly class ReferralPaymentData
{
    public function __construct(
        public string $workspaceId,
        public string $reference,
        public ?string $paymentIntentId,
        public ReferralPaymentSource $source,
        public int $amountCents,
        public string $currency = 'usd',
        public ?string $planId = null,
        public ?BillingInterval $interval = null,
    ) {}
}
