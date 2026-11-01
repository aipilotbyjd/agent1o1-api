<?php

namespace App\Services\Referrals;

use App\Enums\Billing\BillingInterval;
use App\Enums\Referrals\ReferralPaymentSource;

/**
 * What happened, as far as a rule's `conditions` care. Payment fields are
 * null for the non-payment triggers.
 */
final readonly class TriggerContext
{
    public function __construct(
        public ?int $paymentCents = null,
        public ?string $planId = null,
        public ?BillingInterval $interval = null,
        public ?ReferralPaymentSource $paymentSource = null,
        public ?int $paymentSequence = null,
        public ?string $paymentReference = null,
        public ?int $convertedReferrals = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            paymentCents: isset($input['payment_cents']) ? (int) $input['payment_cents'] : null,
            planId: $input['plan_id'] ?? null,
            interval: isset($input['billing_interval']) ? BillingInterval::from($input['billing_interval']) : null,
            paymentSource: isset($input['payment_source']) ? ReferralPaymentSource::from($input['payment_source']) : null,
            paymentSequence: isset($input['payment_sequence']) ? (int) $input['payment_sequence'] : null,
            paymentReference: $input['payment_reference'] ?? null,
            convertedReferrals: isset($input['converted_referrals']) ? (int) $input['converted_referrals'] : null,
        );
    }
}
