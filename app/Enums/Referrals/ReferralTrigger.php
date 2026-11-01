<?php

namespace App\Enums\Referrals;

/**
 * The moments a reward rule can fire on. Each one is a hook in code (see
 * `ReferralLifecycle`), which is why this list is fixed while everything a
 * rule *gives* is configured in the database.
 *
 * `Manual` is never selectable on a rule: it labels a goodwill reward an
 * admin granted by hand.
 */
enum ReferralTrigger: string
{
    case SignupVerified = 'signup_verified';
    case Activated = 'activated';
    case FirstPayment = 'first_payment';
    case RepeatPayment = 'repeat_payment';
    case Milestone = 'milestone';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::SignupVerified => 'Signs up and verifies their email',
            self::Activated => 'Starts actively using the product',
            self::FirstPayment => 'Makes their first payment',
            self::RepeatPayment => 'Makes a later payment',
            self::Milestone => 'Referrer reaches a number of paying referrals',
            self::Manual => 'Granted by an admin',
        };
    }

    /**
     * Payment-driven triggers hold their rewards for the program's
     * `default_hold_days`, so a refund or chargeback can cancel them first.
     */
    public function isPaymentBased(): bool
    {
        return in_array($this, [self::FirstPayment, self::RepeatPayment, self::Milestone], true);
    }

    /**
     * @return list<string>
     */
    public static function ruleValues(): array
    {
        return array_values(array_map(
            fn (self $trigger): string => $trigger->value,
            array_filter(self::cases(), fn (self $trigger): bool => $trigger !== self::Manual),
        ));
    }
}
