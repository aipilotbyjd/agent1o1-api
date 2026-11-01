<?php

namespace App\Enums\Billing;

/**
 * `Expired` is written by `billing:expire-plan-grants` once a fixed-term
 * grant's `expires_at` passes, so the lapse is processed exactly once. A
 * grant still reading `Active` past its `expires_at` already stops
 * entitling (`PlanGrant::scopeActive()` checks the date) it just hasn't
 * had its usage period re-sized yet.
 */
enum PlanGrantStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
