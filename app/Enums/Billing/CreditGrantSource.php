<?php

namespace App\Enums\Billing;

/**
 * Where a block of credits in a workspace's wallet came from. The source
 * decides the default spend priority and whether the grant is part of the
 * period's allowance (plan, free, trial) or carries over between periods
 * (everything else, mirrored into `workspaces.topup_credits`).
 */
enum CreditGrantSource: string
{
    case Plan = 'plan';
    case Free = 'free';
    case Trial = 'trial';
    case Topup = 'topup';
    case Rollover = 'rollover';
    case Promo = 'promo';
    case Referral = 'referral';
    case Admin = 'admin';
    case Migration = 'migration';

    /**
     * Allowance grants belong to one usage period and lapse with it.
     */
    public function isAllowance(): bool
    {
        return in_array($this, [self::Plan, self::Free, self::Trial], true);
    }

    /**
     * Lower is spent first when two grants expire at the same time. Paid
     * top-ups always go last: they never expire and the customer paid for
     * them outright.
     */
    public function defaultPriority(): int
    {
        return match ($this) {
            self::Trial => 10,
            self::Free => 20,
            self::Rollover => 25,
            self::Plan => 30,
            self::Promo => 40,
            self::Referral => 50,
            self::Admin => 60,
            self::Migration => 80,
            self::Topup => 90,
        };
    }

    /**
     * @return list<self>
     */
    public static function carryOver(): array
    {
        return array_values(array_filter(self::cases(), fn (self $source): bool => ! $source->isAllowance()));
    }

    /**
     * @return list<self>
     */
    public static function allowance(): array
    {
        return array_values(array_filter(self::cases(), fn (self $source): bool => $source->isAllowance()));
    }
}
