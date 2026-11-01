<?php

namespace App\Exceptions;

/**
 * Thrown by `Services\Billing\CreditGate` before work starts and by
 * `Actions\Billing\DeductCreditsAction` when the workspace's wallet (and
 * any overage it opted into) cannot pay for a charge: mapped to 402 in
 * `bootstrap/app.php`.
 */
class InsufficientCreditsException extends UsageBlockedException
{
    public function __construct(
        public readonly int $creditsNeeded,
        public readonly int $creditsAvailable,
    ) {
        parent::__construct("Insufficient credits: this action needs {$creditsNeeded}, {$creditsAvailable} remaining.");
    }
}
