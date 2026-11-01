<?php

namespace App\Enums\Billing;

/**
 * The outcome of a free-allowance or trial check. `ShadowDenied` means the
 * check would have denied it but enforcement is in shadow mode, so the
 * credits were granted anyway and the row exists to tune the checks.
 */
enum ClaimDecision: string
{
    case Granted = 'granted';
    case Denied = 'denied';
    case ShadowDenied = 'shadow_denied';

    public function grantsCredits(): bool
    {
        return $this !== self::Denied;
    }
}
