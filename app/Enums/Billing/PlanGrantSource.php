<?php

namespace App\Enums\Billing;

/**
 * Why a workspace holds a `PlanGrant`. `LifetimePurchase` comes from
 * checkout and `Referral` from the referral program's plan-time rewards;
 * the others exist so a comped or promotional grant is a row rather than a
 * new code path.
 */
enum PlanGrantSource: string
{
    case LifetimePurchase = 'lifetime_purchase';
    case Manual = 'manual';
    case Promo = 'promo';
    case Referral = 'referral';
}
